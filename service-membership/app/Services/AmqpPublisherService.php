<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * AmqpPublisherService — Modul 3: AMQP Publisher
 *
 * Menyebarkan event notifikasi secara asinkron ke RabbitMQ Cloud Dosen
 * setelah transaksi membership berhasil diproses.
 *
 * Exchange: iae.central.exchange
 */
class AmqpPublisherService
{
    protected string $ssoBaseUrl;

    public function __construct()
    {
        $this->ssoBaseUrl = config('services.iae_sso.base_url', 'https://iae-sso.virtualfri.id');
    }

    /**
     * Publish event ke RabbitMQ Cloud Dosen melalui HTTP API endpoint.
     *
     * @param  string  $eventType   Tipe event (e.g. 'membership.verified', 'voucher.claimed')
     * @param  array   $payload     Data event
     * @param  string  $bearerToken JWT Bearer token dari SSO Dosen
     * @return array{success: bool, message: string, response: array|null}
     */
    public function publish(string $eventType, array $payload, string $bearerToken): array
    {
        try {
            $message = [
                'event_type'  => $eventType,
                'source'      => 'dpark-membership-service',
                'timestamp'   => now()->toIso8601String(),
                'data'        => $payload,
            ];

            if (isset($payload['approved_by'])) {
                $message['approved_by'] = $payload['approved_by'];
            }

            Log::info('[AMQP] Mengirim event ke RabbitMQ', [
                'event_type' => $eventType,
                'exchange'   => 'iae.central.exchange',
            ]);

            $response = Http::timeout(10)
                ->withToken($bearerToken)
                ->post("{$this->ssoBaseUrl}/api/v1/messages/publish", [
                    'exchange'     => 'iae.central.exchange',
                    'routing_key'  => $eventType,
                    'message'      => $message,
                ]);

            if ($response->successful()) {
                Log::info('[AMQP] Event berhasil dipublish', [
                    'event_type' => $eventType,
                    'status'     => $response->status(),
                ]);

                return [
                    'success'  => true,
                    'message'  => 'Event berhasil dipublish ke RabbitMQ.',
                    'response' => $response->json(),
                ];
            }

            Log::warning('[AMQP] Gagal publish event', [
                'event_type' => $eventType,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);

            return [
                'success'  => false,
                'message'  => 'Gagal publish event: HTTP ' . $response->status() . ' - ' . $response->body(),
                'response' => $response->json(),
            ];
        } catch (Exception $e) {
            Log::error('[AMQP] Exception saat publish', ['error' => $e->getMessage()]);

            return [
                'success'  => false,
                'message'  => 'AMQP error: ' . $e->getMessage(),
                'response' => null,
            ];
        }
    }

    /**
     * Publish event MembershipVerified.
     * Dipanggil setelah verifikasi membership berhasil.
     */
    public function publishMembershipVerified(
        string $vehiclePlate,
        string $memberNumber,
        string $memberName,
        string $membershipType,
        int $discountPercentage,
        string $bearerToken,
        array $approvedBy = []
    ): array {
        $payload = [
            'vehicle_plate'       => $vehiclePlate,
            'member_number'       => $memberNumber,
            'member_name'         => $memberName,
            'membership_type'     => $membershipType,
            'discount_percentage' => $discountPercentage,
            'verified_at'         => now()->toIso8601String(),
        ];

        if (!empty($approvedBy)) {
            $payload['approved_by'] = $approvedBy;
        }

        return $this->publish('membership.verified', $payload, $bearerToken);
    }


    /**
     * Publish event MembershipExpired.
     * Dipanggil saat membership kadaluarsa terdeteksi.
     */
    public function publishMembershipExpired(
        string $memberNumber,
        string $memberName,
        string $expiredAt,
        string $bearerToken
    ): array {
        return $this->publish('membership.expired', [
            'member_number' => $memberNumber,
            'member_name'   => $memberName,
            'expired_at'    => $expiredAt,
            'detected_at'   => now()->toIso8601String(),
        ], $bearerToken);
    }

    /**
     * Publish event VoucherClaimed.
     * Dipanggil setelah member berhasil mengklaim voucher.
     */
    public function publishVoucherClaimed(
        string $memberNumber,
        string $voucherCode,
        string $discountType,
        float $discountValue,
        string $bearerToken
    ): array {
        return $this->publish('voucher.claimed', [
            'member_number'  => $memberNumber,
            'voucher_code'   => $voucherCode,
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
            'claimed_at'     => now()->toIso8601String(),
        ], $bearerToken);
    }
}
