<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaidOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ChapaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected ChapaService $chapaService) {}

    /**
     * Handle incoming Chapa webhook notification.
     */
    public function handleChapa(Request $request): JsonResponse
    {
        $signature = $request->header('x-chapa-signature') ?? $request->header('Chapa-Signature');
        $rawPayload = $request->getContent();

        if (! $this->chapaService->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('Chapa Webhook: Invalid signature attempt.', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $payload = $request->all();
        $txRef = $payload['tx_ref'] ?? $payload['trx_ref'] ?? null;

        if (! $txRef) {
            return response()->json([
                'message' => 'Transaction reference is missing in payload.',
            ], 400);
        }

        $payment = Payment::where('tx_ref', $txRef)->first();
        $order = $payment?->order ?? Order::where('tx_ref', $txRef)->first();

        if (! $payment || ! $order) {
            Log::warning("Chapa Webhook: Payment or order not found for tx_ref '{$txRef}'.");

            return response()->json([
                'message' => 'Associated order or payment record not found.',
            ], 404);
        }

        // Idempotency: avoid re-processing already successful transactions
        if ($payment->status === 'success' || $order->isPaid()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Payment has already been successfully processed.',
            ]);
        }

        $isSuccess = strtolower((string) ($payload['status'] ?? '')) === 'success';

        DB::transaction(function () use ($payment, $order, $payload, $isSuccess) {
            $payment->update([
                'status' => $isSuccess ? 'success' : 'failed',
                'chapa_reference' => $payload['reference'] ?? null,
                'channel' => $payload['payment_method'] ?? ($payload['channel'] ?? null),
                'webhook_payload' => $payload,
                'verified_at' => now(),
            ]);

            if ($isSuccess) {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                // Dispatch async job for stock decrement and confirmation
                ProcessPaidOrder::dispatch($order->id);
            } else {
                $order->update([
                    'status' => 'failed',
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook processed successfully.',
        ]);
    }
}
