<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

        $transaction = Transaction::create([
            'plate_number' => $request->plate_number,
            'location_id'  => $request->location_id,
            'vehicle_type' => $request->vehicle_type,
            'entry_time'   => now(),
            'status'       => 'ongoing',
            'member_id'    => $request->member_id,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Transaction created successfully',
            'data'    => $transaction,
            'meta'    => ['service_name' => 'Transaction-Service', 'api_version' => 'v1'],
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
}