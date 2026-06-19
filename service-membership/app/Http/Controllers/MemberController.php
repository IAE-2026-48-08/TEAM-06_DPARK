<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyMemberRequest;
use App\Models\Member;
use App\Services\AmqpPublisherService;
use App\Services\MembershipService;
use App\Services\SoapAuditService;
use App\Services\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class MemberController extends Controller
{
    public function __construct(
        protected MembershipService  $membershipService,
        protected SsoService         $ssoService,
        protected SoapAuditService   $soapAuditService,
        protected AmqpPublisherService $amqpPublisher
    ) {}

    #[OA\Get(
        path: "/members",
        summary: "Get list of all members",
        security: [["ApiKeyAuth" => []]],
        tags: ["Member"]
    )]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function index(): JsonResponse
    {
        $members = Member::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar seluruh member berhasil diambil.',
            'data'    => $members,
        ]);
    }

    #[OA\Get(
        path: "/members/{id}",
        summary: "Get member details and membership status",
        security: [["ApiKeyAuth" => []]],
        tags: ["Member"]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 404, description: "Member not found")]
    public function show(int $id): JsonResponse
    {
        $member = Member::find($id);

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member tidak ditemukan.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail member berhasil diambil.',
            'data'    => [
                'member'    => $member,
                'is_active' => $member->isActive(),
            ],
        ]);
    }

    /**
     * Verifikasi Membership — Transaksi Kritis
     *
     * Ini adalah endpoint paling kritis di service ini karena:
     * 1. Menentukan diskon finansial yang diterapkan ke pembayaran parkir
     * 2. Menjadi dasar keputusan bisnis state-changing (diskon diterapkan/tidak)
     *
     * Alur integrasi:
     * 1. Proses verifikasi membership
     * 2. Jika member valid → Login SSO Dosen (M2M)
     * 3. Kirim SOAP audit ke Cloud Dosen → simpan ReceiptNumber
     * 4. Broadcast event ke RabbitMQ Cloud Dosen
     */
    #[OA\Post(
        path: "/members/verification",
        summary: "Verify membership during parking transaction (Critical Transaction)",
        security: [["ApiKeyAuth" => []]],
        tags: ["Member"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "vehicle_plate", type: "string", example: "D 1234 ABC"),
                new OA\Property(property: "subtotal", type: "number", nullable: true, example: 10000)
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Verification result with audit integration")]
    public function verify(VerifyMemberRequest $request): JsonResponse
    {
        // ─── Step 1: Proses Verifikasi Membership ─────────────────────────
        $result = $this->membershipService->verifyMembership($request->vehicle_plate);

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data'    => [
                    'vehicle_plate'       => $request->vehicle_plate,
                    'is_member'           => false,
                    'discount_percentage' => 0,
                ],
            ], 200);
        }

        $responseData = [
            'vehicle_plate'       => $request->vehicle_plate,
            'is_member'           => true,
            'member_id'           => $result['member']->id,
            'member_number'       => $result['member']->member_number,
            'member_name'         => $result['member']->name,
            'membership_type'     => $result['member']->membership_type,
            'discount_percentage' => $result['discount_percentage'],
        ];

        if ($request->filled('subtotal')) {
            $calc = $this->membershipService->applyMembershipDiscount(
                (float) $request->subtotal,
                $result['discount_percentage']
            );
            $responseData['calculation'] = $calc;
        }

        // ─── Step 1.5: Dapatkan SSO Subject & Roles dari Token Request (jika ada) ───
        $ssoSubject = null; // akan diisi dari JWT
        $ssoRoles   = ['membership_operator'];

        $incomingToken = $request->bearerToken();
        if ($incomingToken) {
            try {
                $decodedPayload = $this->ssoService->verifyAndDecodeJwt($incomingToken);
                if ($decodedPayload) {
                    $ssoSubject = $decodedPayload['sub'] ?? $decodedPayload['email'] ?? $decodedPayload['user_id'] ?? $ssoSubject;
                    $rolesFromJwt = $decodedPayload['roles'] ?? $decodedPayload['role'] ?? [];
                    if (!empty($rolesFromJwt)) {
                        $ssoRoles = is_array($rolesFromJwt) ? $rolesFromJwt : [$rolesFromJwt];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('[Verify] Gagal decode incoming token, menggunakan fallback subject', ['error' => $e->getMessage()]);
            }
        }

        // ─── Step 2: Login M2M ke SSO Dosen ──────────────────────────────
        $ssoToken       = null;
        $ssoLoginResult = ['success' => false, 'message' => 'Skipped'];

        try {
            $ssoToken       = $this->ssoService->getCachedM2mToken();
            $ssoLoginResult = ['success' => (bool) $ssoToken, 'message' => $ssoToken ? 'Login SSO berhasil.' : 'Login SSO gagal.'];

            Log::info('[Verify] SSO M2M login', ['success' => (bool) $ssoToken]);

            // Jika ssoSubject masih null, ambil dari M2M token
            if (!$ssoSubject && $ssoToken) {
                try {
                    $m2mPayload = $this->ssoService->verifyAndDecodeJwt($ssoToken);
                    if ($m2mPayload) {
                        $ssoSubject = $m2mPayload['sub'] ?? $m2mPayload['app']['client_id'] ?? 'KEY-MHS-169';
                    }
                } catch (\Exception $e) {
                    Log::warning('[Verify] Gagal decode M2M token untuk subject', ['error' => $e->getMessage()]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('[Verify] SSO login gagal, lanjut tanpa audit', ['error' => $e->getMessage()]);
        }

        // Fallback jika masih null
        if (!$ssoSubject) {
            $ssoSubject = 'KEY-MHS-169';
        }

        $approvedBy = [
            'sso_subject' => $ssoSubject,
            'roles'       => $ssoRoles,
        ];


        $responseData['integrations'] = [
            'sso' => [
                'success' => $ssoLoginResult['success'],
                'message' => $ssoLoginResult['message'],
            ],
        ];

        // ─── Step 3: Kirim SOAP Audit ke Cloud Dosen ─────────────────────
        if ($ssoToken) {
            try {
                $soapResult = $this->soapAuditService->auditMembershipVerification(
                    vehiclePlate:       $request->vehicle_plate,
                    memberNumber:       $result['member']->member_number,
                    memberName:         $result['member']->name,
                    membershipType:     $result['member']->membership_type,
                    discountPercentage: $result['discount_percentage'],
                    bearerToken:        $ssoToken,
                    approvedBy:         $approvedBy
                );

                $responseData['integrations']['soap_audit'] = [
                    'success'        => $soapResult['success'],
                    'receipt_number' => $soapResult['receipt_number'],
                    'message'        => $soapResult['message'],
                ];

                Log::info('[Verify] SOAP audit selesai', [
                    'success'        => $soapResult['success'],
                    'receipt_number' => $soapResult['receipt_number'],
                ]);
            } catch (\Exception $e) {
                Log::warning('[Verify] SOAP audit gagal', ['error' => $e->getMessage()]);
                $responseData['integrations']['soap_audit'] = [
                    'success' => false,
                    'message' => 'SOAP audit error: ' . $e->getMessage(),
                ];
            }

            // ─── Step 4: Broadcast Event ke RabbitMQ ─────────────────────
            try {
                $amqpResult = $this->amqpPublisher->publishMembershipVerified(
                    vehiclePlate:       $request->vehicle_plate,
                    memberNumber:       $result['member']->member_number,
                    memberName:         $result['member']->name,
                    membershipType:     $result['member']->membership_type,
                    discountPercentage: $result['discount_percentage'],
                    bearerToken:        $ssoToken,
                    approvedBy:         $approvedBy
                );

                $responseData['integrations']['amqp'] = [
                    'success' => $amqpResult['success'],
                    'message' => $amqpResult['message'],
                ];

                Log::info('[Verify] AMQP event published', ['success' => $amqpResult['success']]);
            } catch (\Exception $e) {
                Log::warning('[Verify] AMQP publish gagal', ['error' => $e->getMessage()]);
                $responseData['integrations']['amqp'] = [
                    'success' => false,
                    'message' => 'AMQP error: ' . $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $responseData,
        ]);
    }
}
