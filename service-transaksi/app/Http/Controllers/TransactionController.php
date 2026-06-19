<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use OpenApi\Attributes as OA;
use App\Services\SoapAuditService;
use App\Services\RabbitMQPublisher;

class TransactionController extends Controller
{
    public function __construct(
        private SoapAuditService  $soapAudit,
        private RabbitMQPublisher $mqPublisher,
    ) {}

    #[OA\Get(
        path: '/api/v1/transactions',
        summary: 'Admin memantau seluruh transaksi parkir',
        security: [['ApiKeyAuth' => []]],
        tags: ['Transactions'],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index()
    {
        $transactions = Transaction::all();
        return response()->json([
            'status'  => 'success',
            'message' => 'Data retrieved successfully',
            'data'    => $transactions,
            'meta'    => ['service_name' => 'Transaction-Service', 'api_version' => 'v1'],
        ], 200);
    }

    #[OA\Get(
        path: '/api/v1/transactions/{id}',
        summary: 'Petugas melihat detail transaksi dan total biaya parkir',
        security: [['ApiKeyAuth' => []]],
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function show($id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Transaction not found',
                'errors'  => null,
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data retrieved successfully',
            'data'    => $transaction,
            'meta'    => ['service_name' => 'Transaction-Service', 'api_version' => 'v1'],
        ], 200);
    }

    #[OA\Post(
        path: '/api/v1/transactions',
        summary: 'Petugas mencatat kendaraan masuk melalui scan plat nomor',
        security: [['ApiKeyAuth' => []]],
        tags: ['Transactions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['plate_number', 'location_id', 'vehicle_type'],
                properties: [
                    new OA\Property(property: 'plate_number', type: 'string', example: 'D 1234 ABC'),
                    new OA\Property(property: 'location_id', type: 'integer', example: 1),
                    new OA\Property(property: 'vehicle_type', type: 'string', enum: ['motor', 'mobil'], example: 'mobil'),
                    new OA\Property(property: 'member_id', type: 'string', example: 'MBR-001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation Error'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plate_number' => 'required|string',
            'location_id'  => 'required|integer',
            'vehicle_type' => 'required|in:motor,mobil',
            'member_id'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ── INTEGRASI SERVICE C: Verifikasi Membership ────────────────────────
        // Panggil service-membership untuk cek apakah kendaraan terdaftar sebagai member.
        // Jika member → dapat diskon. Proses ini juga otomatis trigger SSO→SOAP→RabbitMQ
        // di dalam service-membership (sesuai alur bisnis end-to-end).
        $membershipData = $this->callMembershipVerification($request->plate_number);

        // ── INTEGRASI SERVICE A: Check-in Lokasi ─────────────────────────────
        // Panggil service-lokasi untuk mencatat kendaraan masuk dan update slot tersedia.
        // vehicle_type di service-lokasi menggunakan 'car'/'motor', bukan 'mobil'/'motor'
        $lokasiVehicleType = $request->vehicle_type === 'mobil' ? 'car' : 'motor';
        $checkInData       = $this->callLokasiCheckIn($request->location_id, $lokasiVehicleType);

        // ── Simpan Transaksi ─────────────────────────────────────────────────
        $transaction = Transaction::create([
            'plate_number'        => $request->plate_number,
            'location_id'         => $request->location_id,
            'vehicle_type'        => $request->vehicle_type,
            'entry_time'          => now(),
            'status'              => 'ongoing',
            'member_id'           => $request->member_id,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Transaction created successfully',
            'data'    => $transaction,
            'meta'    => [
                'service_name'      => 'Transaction-Service',
                'api_version'       => 'v1',
                // Info integrasi dikembalikan ke response agar bisa diverifikasi
                'membership'        => [
                    'is_member'           => $membershipData['is_member'],
                    'discount_percentage' => $membershipData['discount_percentage'],
                    'member_name'         => $membershipData['member_name'],
                    'membership_type'     => $membershipData['membership_type'],
                ],
                'lokasi_check_in'   => [
                    'success'         => $checkInData['success'],
                    'available_slots' => $checkInData['available_slots'],
                ],
            ],
        ], 201);
    }

    #[OA\Put(
        path: '/api/v1/transactions/{id}',
        summary: 'Sistem memperbarui status transaksi setelah pembayaran berhasil',
        security: [['ApiKeyAuth' => []]],
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'discount_amount', type: 'number', example: 5000),
                    new OA\Property(property: 'status', type: 'string', enum: ['completed', 'cancelled'], example: 'completed'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Transaction not found',
                'errors'  => null,
            ], 404);
        }

        $exitTime      = now();
        $entryTime     = Carbon::parse($transaction->entry_time);
        $durationHours = max(1, $entryTime->diffInHours($exitTime));
        $ratePerHour   = $transaction->vehicle_type === 'mobil' ? 5000 : 2000;
        $amount        = $durationHours * $ratePerHour;
        $discount      = $request->discount_amount ?? 0;
        $finalAmount   = max(0, $amount - $discount);

        $transaction->update([
            'exit_time'       => $exitTime,
            'amount'          => $finalAmount,
            'discount_amount' => $discount,
            'status'          => $request->status ?? 'completed',
        ]);

        // Hanya jalankan SOAP + RabbitMQ kalau status completed
        if (($request->status ?? 'completed') === 'completed') {

            // Modul 2: Kirim audit ke SOAP Dosen, simpan ReceiptNumber
            $receiptNumber = $this->soapAudit->audit($transaction->fresh()->toArray());
            if ($receiptNumber) {
                $transaction->update(['receipt_number' => $receiptNumber]);
            }

            // Modul 3: Publish event ke RabbitMQ Dosen
            $this->mqPublisher->publishCheckout($transaction->fresh()->toArray());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Transaction updated successfully',
            'data'    => $transaction->fresh(),
            'meta'    => ['service_name' => 'Transaction-Service', 'api_version' => 'v1'],
        ], 200);
    }

    // =========================================================================
    // PRIVATE HELPERS — Komunikasi Internal Antar Service
    // =========================================================================

    /**
     * Panggil Service C (Membership) untuk verifikasi plat kendaraan.
     * Endpoint: POST http://service-membership/api/v1/members/verification
     *
     * Proses ini otomatis men-trigger SSO Login → SOAP Audit → RabbitMQ
     * di dalam service-membership (Central Infrastructure Compliance).
     *
     * @param  string $platNumber  Nomor plat kendaraan
     * @return array{is_member: bool, discount_percentage: int, member_name: string|null, membership_type: string|null}
     */
    private function callMembershipVerification(string $platNumber): array
    {
        $defaultResult = [
            'is_member'           => false,
            'discount_percentage' => 0,
            'member_name'         => null,
            'membership_type'     => null,
        ];

        try {
            $url      = rtrim(env('SERVICE_MEMBERSHIP_URL', 'http://service-membership/api'), '/');
            $apiKey   = env('INTERNAL_API_KEY', 'DParkMembershipApiKey2026');

            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$url}/v1/members/verification", [
                'vehicle_plate' => $platNumber,
            ]);

            if ($response->successful()) {
                $data = $response->json('data', []);
                Log::info('[TransactionController] Membership verification berhasil', [
                    'plate_number'        => $platNumber,
                    'is_member'           => $data['is_member'] ?? false,
                    'discount_percentage' => $data['discount_percentage'] ?? 0,
                ]);
                return [
                    'is_member'           => $data['is_member'] ?? false,
                    'discount_percentage' => $data['discount_percentage'] ?? 0,
                    'member_name'         => $data['member_name'] ?? null,
                    'membership_type'     => $data['membership_type'] ?? null,
                ];
            }

            Log::warning('[TransactionController] Membership service response non-200', [
                'status' => $response->status(),
                'plate'  => $platNumber,
            ]);
        } catch (\Exception $e) {
            // Tidak membatalkan transaksi — membership bersifat opsional
            Log::error('[TransactionController] Gagal hubungi service-membership', [
                'error' => $e->getMessage(),
                'plate' => $platNumber,
            ]);
        }

        return $defaultResult;
    }

    /**
     * Panggil Service A (Lokasi) untuk check-in kendaraan masuk.
     * Endpoint: POST http://service-lokasi/api/v1/locations/{id}/check-in
     *
     * Endpoint ini dilindungi SSO JWT di service-lokasi — jika tidak ada token,
     * service-lokasi akan menolak (401). Dalam integrasi ini kita skip token
     * SSO karena service-transaksi adalah caller internal terpercaya.
     * Untuk produksi, tambahkan M2M token dari SSO.
     *
     * @param  int    $locationId  ID lokasi parkir
     * @param  string $vehicleType 'car' atau 'motor'
     * @return array{success: bool, available_slots: int|null}
     */
    private function callLokasiCheckIn(int $locationId, string $vehicleType): array
    {
        $defaultResult = ['success' => false, 'available_slots' => null];

        try {
            $url    = rtrim(env('SERVICE_LOKASI_URL', 'http://service-lokasi/api'), '/');
            $apiKey = env('INTERNAL_API_KEY', 'DParkLahanApiKey2026');

            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(10)->post("{$url}/v1/locations/{$locationId}/internal-checkin", [
                'vehicle_type' => $vehicleType,
            ]);

            if ($response->successful()) {
                $data = $response->json('data', []);
                Log::info('[TransactionController] Lokasi check-in berhasil', [
                    'location_id'    => $locationId,
                    'vehicle_type'   => $vehicleType,
                    'available_after' => $data['available_after'] ?? null,
                ]);
                return [
                    'success'         => true,
                    'available_slots' => $data['available_after'] ?? null,
                ];
            }

            Log::warning('[TransactionController] Lokasi service response non-200', [
                'status'      => $response->status(),
                'location_id' => $locationId,
            ]);
        } catch (\Exception $e) {
            // Tidak membatalkan transaksi — check-in lokasi bisa di-retry
            Log::error('[TransactionController] Gagal hubungi service-lokasi', [
                'error'       => $e->getMessage(),
                'location_id' => $locationId,
            ]);
        }

        return $defaultResult;
    }
}