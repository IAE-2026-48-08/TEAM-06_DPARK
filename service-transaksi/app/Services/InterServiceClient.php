<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * InterServiceClient
 * 
 * Menangani komunikasi internal antar microservice DPark Bandung.
 * Dipanggil dari TransactionController saat membuat transaksi baru.
 */
class InterServiceClient
{
    private string $lokasiUrl;
    private string $membershipUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->lokasiUrl    = env('SERVICE_LOKASI_URL', 'http://service-lokasi/api');
        $this->membershipUrl = env('SERVICE_MEMBERSHIP_URL', 'http://service-membership/api');
        $this->apiKey       = env('INTERNAL_API_KEY', 'DParkLahanApiKey2026');
    }

    /**
     * Verifikasi membership pelanggan ke Service C.
     * Otomatis trigger SSO → SOAP Audit → RabbitMQ di service-membership.
     *
     * @param string $vehiclePlate Nomor plat kendaraan
     * @param float  $subtotal     Biaya parkir sebelum diskon
     * @return array{is_member: bool, discount_percentage: int, calculation: array|null}
     */
    public function verifyMembership(string $vehiclePlate, float $subtotal = 0): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key'    => $this->apiKey,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$this->membershipUrl}/v1/members/verification", [
                'vehicle_plate' => $vehiclePlate,
                'subtotal'      => $subtotal,
            ]);

            if ($response->successful()) {
                $data = $response->json('data', []);
                Log::info('[InterService] Membership verification success', [
                    'vehicle_plate'       => $vehiclePlate,
                    'is_member'           => $data['is_member'] ?? false,
                    'discount_percentage' => $data['discount_percentage'] ?? 0,
                ]);
                return [
                    'is_member'           => $data['is_member'] ?? false,
                    'discount_percentage' => $data['discount_percentage'] ?? 0,
                    'member_name'         => $data['member_name'] ?? null,
                    'membership_type'     => $data['membership_type'] ?? null,
                    'calculation'         => $data['calculation'] ?? null,
                    'integrations'        => $data['integrations'] ?? [],
                ];
            }

            Log::warning('[InterService] Membership verification non-200', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('[InterService] Membership verification failed', ['error' => $e->getMessage()]);
        }

        // Fallback: tidak ada diskon jika service membership tidak bisa dihubungi
        return [
            'is_member'           => false,
            'discount_percentage' => 0,
            'member_name'         => null,
            'membership_type'     => null,
            'calculation'         => null,
            'integrations'        => [],
        ];
    }

    /**
     * Update slot parkir di Service A setelah transaksi dibuat.
     * Menggunakan SSO JWT — service-lokasi akan verifikasi token.
     *
     * @param int    $locationId  ID lokasi parkir
     * @param string $vehicleType 'car' atau 'motor'
     * @param string $ssoToken    JWT token dari SSO (jika ada)
     * @return array{success: bool, available_slots: int|null}
     */
    public function checkInLocation(int $locationId, string $vehicleType, string $ssoToken = ''): array
    {
        try {
            $headers = [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ];

            // Service A endpoint check-in pakai SSO JWT middleware
            if ($ssoToken) {
                $headers['Authorization'] = "Bearer {$ssoToken}";
            }

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post("{$this->lokasiUrl}/v1/locations/{$locationId}/check-in", [
                    'vehicle_type' => $vehicleType,
                ]);

            if ($response->successful()) {
                $data = $response->json('data', []);
                Log::info('[InterService] Location check-in success', [
                    'location_id'    => $locationId,
                    'vehicle_type'   => $vehicleType,
                    'available_after' => $data['available_after'] ?? null,
                ]);
                return [
                    'success'         => true,
                    'available_slots' => $data['available_after'] ?? null,
                ];
            }

            Log::warning('[InterService] Location check-in non-200', [
                'location_id' => $locationId,
                'status'      => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('[InterService] Location check-in failed', ['error' => $e->getMessage()]);
        }

        return ['success' => false, 'available_slots' => null];
    }
}