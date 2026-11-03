<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ChapaService
{
    protected string $baseUrl;

    protected ?string $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.chapa.base_url', 'https://api.chapa.co/v1');
        $this->secretKey = config('services.chapa.secret_key');
    }

    /**
     * Initialize transaction with Chapa payment gateway.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function initializePayment(array $data): array
    {
        $rawDescription = 'Payment for Order '.($data['order_number'] ?? '');
        // Chapa allows only letters, numbers, hyphens, underscores, spaces, and dots.
        $sanitizedDescription = trim((string) preg_replace('/[^a-zA-Z0-9\-_ .]/', '', $rawDescription));

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'ETB',
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'tx_ref' => $data['tx_ref'],
                'callback_url' => route('api.webhooks.chapa'),
                'return_url' => $data['return_url'],
                'customization' => [
                    'title' => 'Order Payment',
                    'description' => $sanitizedDescription,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Chapa initialization failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Verify transaction with Chapa payment gateway.
     *
     * @param  string  $txRef
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function verifyPayment(string $txRef): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$txRef}");

        if (! $response->successful()) {
            throw new RuntimeException('Chapa verification failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Verify incoming webhook signature against configured secret.
     */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.chapa.webhook_secret', $this->secretKey);

        if (empty($secret) || empty($signature) || empty($payload)) {
            return false;
        }

        $computedSignature = hash_hmac('sha256', $payload, (string) $secret);

        return hash_equals($computedSignature, trim((string) $signature));
    }
}
