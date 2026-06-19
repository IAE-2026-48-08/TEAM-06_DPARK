<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RabbitMQPublisher
{
    public function publishCheckout(array $transaction): bool
    {
        // Langkah 1: Minta token dulu ke SSO Dosen
        $token = $this->getToken();

        if (!$token) {
            Log::error('[AMQP] Gagal mendapatkan token SSO');
            return false;
        }

        // Langkah 2: Siapkan isi pesan yang akan dikirim
        $payload = [
            'routing_key' => 'transaction.completed',
            'message'     => [
                'event'          => 'transaction.completed',
                'service'        => 'DiPark-Transaction-Service',
                'transaction_id' => $transaction['id'],
                'plate_number'   => $transaction['plate_number'],
                'location_id'    => $transaction['location_id'],
                'vehicle_type'   => $transaction['vehicle_type'],
                'amount'         => $transaction['amount'],
                'receipt_number' => $transaction['receipt_number'] ?? null,
                'timestamp'      => now()->toIso8601String(),
            ],
        ];

        // Langkah 3: Kirim ke endpoint RabbitMQ Dosen via HTTP
        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->post(env('MQ_PUBLISH_URL'), $payload);

            if ($response->successful()) {
                Log::info('[AMQP] Event berhasil dipublish: transaction.completed');
                return true;
            }

            Log::error('[AMQP] Gagal publish: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('[AMQP] Exception: ' . $e->getMessage());
            return false;
        }
    }

    private function getToken(): ?string
    {
        try {
            $response = Http::withoutVerifying()
                ->post(env('SSO_DOSEN_URL') . '/api/v1/auth/token', [
                    'api_key' => env('SSO_API_KEY'),
                    'nim' => env('IAE_NIM'),
                ]);

            return $response->json('token');
        } catch (\Exception $e) {
            Log::error('[SSO] Gagal minta token: ' . $e->getMessage());
            return null;
        }
    }
}
