<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeniusPayService
{
    private string $base = 'https://pay.genius.ci/api/v1/merchant';

    public function initializePayment(int $amount, string $orderId, string $phone, string $callbackUrl): array
    {
        $response = Http::withHeaders([
            'X-API-Key'    => config('services.genius_pay.api_key'),
            'X-API-Secret' => config('services.genius_pay.api_secret'),
        ])->post("{$this->base}/payments", [
            'amount'       => $amount,
            'currency'     => 'XOF',
            'customer'     => ['phone' => $phone],
            'metadata'     => ['order_id' => $orderId],
            'callback_url' => $callbackUrl,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Genius Pay error: ' . $response->body());
        }

        return $response->json('data', $response->json());
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        $timestamp  = $request->header('X-Webhook-Timestamp');

        if (!$signature || !$timestamp) return false;
        if (abs(time() - (int) $timestamp) > 300) return false;

        $expected = hash_hmac(
            'sha256',
            $timestamp . '.' . $request->getContent(),
            config('services.genius_pay.webhook_secret')
        );

        return hash_equals($expected, $signature);
    }
}
