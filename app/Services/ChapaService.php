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
     * Get configured HTTP client instance for Chapa gateway requests.
     * Enforces HTTP/1.1 to avoid TLS 1.3/HTTP/2 stream truncation errors with OpenSSL 3.0+.
     */
    protected function client()
    {
        $options = [
            'version' => 1.1,
            'timeout' => 60,
            'connect_timeout' => 30,
        ];

        if (config('services.chapa.verify_ssl') === false || env('CHAPA_VERIFY_SSL') === 'false') {
            $options['verify'] = false;
        }

        return Http::withToken($this->secretKey)
            ->withOptions($options)
            ->retry(2, 200, throw: false);
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

        // Chapa performs strict domain validation; map RFC reserved test domains to a valid format for sandbox testing
        $email = (string) ($data['email'] ?? '');
        if (preg_match('/@(?:example\.(?:com|org|net)|test|invalid|localhost)$/i', $email)) {
            $email = (string) preg_replace('/@(?:example\.(?:com|org|net)|test|invalid|localhost)$/i', '@gmail.com', $email);
        }

        $response = $this->client()
            ->post("{$this->baseUrl}/transaction/initialize", [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'ETB',
                'email' => $email,
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
        $response = $this->client()
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
