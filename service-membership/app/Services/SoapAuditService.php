<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SoapAuditService — Modul 2: SOAP XML Client
 *
 * Menangani audit transaksi kritis (verifikasi membership) ke Cloud Dosen
 * menggunakan protokol SOAP/XML (Legacy Audit System).
 *
 * Transaksi yang diaudit: verifyMembership
 * Alasan: Transaksi ini bersifat kritis karena menentukan diskon finansial
 * yang diterapkan ke pembayaran parkir (state-changing financial transaction).
 */
class SoapAuditService
{
    protected string $soapEndpoint;
    protected string $teamId;

    public function __construct()
    {
        $this->soapEndpoint = config('services.iae_sso.base_url', 'https://iae-sso.virtualfri.id') . '/soap/v1/audit';
        $this->teamId       = config('services.iae_sso.team_id', 'TEAM-08');
    }

    /**
     * Kirim audit log transaksi membership ke SOAP Cloud Dosen.
     *
     * @param  string  $activityName  Nama aktivitas bisnis
     * @param  array   $transactionData  Data transaksi yang akan diaudit
     * @param  string  $bearerToken  JWT token dari SSO Dosen
     * @return array{success: bool, receipt_number: string|null, message: string, raw_response: string|null}
     */
    public function sendAudit(string $activityName, array $transactionData, string $bearerToken): array
    {
        try {
            $soapEnvelope = $this->buildSoapEnvelope($activityName, $transactionData);

            Log::info('[SOAP] Mengirim audit request', [
                'activity'  => $activityName,
                'endpoint'  => $this->soapEndpoint,
                'team_id'   => $this->teamId,
            ]);

            $response = Http::timeout(15)
                ->withToken($bearerToken)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=UTF-8',
                    'SOAPAction'   => 'AuditRequest',
                ])
                ->withBody($soapEnvelope, 'text/xml')
                ->post($this->soapEndpoint);

            $rawResponse = $response->body();

            Log::info('[SOAP] Response diterima', [
                'status'       => $response->status(),
                'raw_response' => substr($rawResponse, 0, 500),
            ]);

            if ($response->successful() || $response->status() === 200) {
                $receiptNumber = $this->parseReceiptNumber($rawResponse);
                $status        = $this->parseStatus($rawResponse);

                if ($status === 'SUCCESS' && $receiptNumber) {
                    Log::info('[SOAP] Audit berhasil', [
                        'receipt_number' => $receiptNumber,
                        'activity'       => $activityName,
                    ]);

                    return [
                        'success'        => true,
                        'receipt_number' => $receiptNumber,
                        'message'        => 'Audit berhasil dikirim ke Cloud Dosen.',
                        'raw_response'   => $rawResponse,
                    ];
                }
            }

            Log::warning('[SOAP] Audit gagal atau response tidak valid', [
                'http_status'  => $response->status(),
                'raw_response' => $rawResponse,
            ]);

            return [
                'success'        => false,
                'receipt_number' => null,
                'message'        => 'SOAP audit gagal. HTTP ' . $response->status(),
                'raw_response'   => $rawResponse,
            ];
        } catch (Exception $e) {
            Log::error('[SOAP] Exception saat kirim audit', ['error' => $e->getMessage()]);

            return [
                'success'        => false,
                'receipt_number' => null,
                'message'        => 'SOAP error: ' . $e->getMessage(),
                'raw_response'   => null,
            ];
        }
    }

    /**
     * Build SOAP XML Envelope sesuai skema Cloud Dosen.
     */
    private function buildSoapEnvelope(string $activityName, array $transactionData): string
    {
        $logContent = json_encode($transactionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $teamId     = htmlspecialchars($this->teamId, ENT_XML1);
        $activity   = htmlspecialchars($activityName, ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
    <soap:Body>
        <iae:AuditRequest>
            <iae:TeamID>{$teamId}</iae:TeamID>
            <iae:ActivityName>{$activity}</iae:ActivityName>
            <iae:LogContent><![CDATA[{$logContent}]]></iae:LogContent>
        </iae:AuditRequest>
    </soap:Body>
</soap:Envelope>
XML;
    }

    /**
     * Parse ReceiptNumber dari SOAP XML response.
     */
    private function parseReceiptNumber(string $xmlResponse): ?string
    {
        // Coba berbagai kemungkinan tag ReceiptNumber
        $patterns = [
            '/<iae:ReceiptNumber>(.*?)<\/iae:ReceiptNumber>/s',
            '/<ReceiptNumber>(.*?)<\/ReceiptNumber>/s',
            '/ReceiptNumber[^>]*>(.*?)<\/[^>]*ReceiptNumber>/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $xmlResponse, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Parse Status dari SOAP XML response.
     */
    private function parseStatus(string $xmlResponse): ?string
    {
        $patterns = [
            '/<iae:Status>(.*?)<\/iae:Status>/s',
            '/<Status>(.*?)<\/Status>/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $xmlResponse, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Audit transaksi MembershipVerification (shorthand method).
     */
    public function auditMembershipVerification(
        string $vehiclePlate,
        string $memberNumber,
        string $memberName,
        string $membershipType,
        int $discountPercentage,
        string $bearerToken,
        array $approvedBy = []
    ): array {
        $data = [
            'vehicle_plate'       => $vehiclePlate,
            'member_number'       => $memberNumber,
            'member_name'         => $memberName,
            'membership_type'     => $membershipType,
            'discount_percentage' => $discountPercentage,
            'verified_at'         => now()->toIso8601String(),
            'service'             => 'DPark-Membership-Service',
        ];

        if (!empty($approvedBy)) {
            $data['approved_by'] = $approvedBy;
        }

        return $this->sendAudit('MembershipVerification', $data, $bearerToken);
    }

    /**
     * Audit transaksi VoucherClaim (shorthand method).
     */
    public function auditVoucherClaim(
        int $memberId,
        string $memberNumber,
        int $voucherId,
        string $voucherCode,
        string $bearerToken
    ): array {
        return $this->sendAudit('VoucherClaimed', [
            'member_id'     => $memberId,
            'member_number' => $memberNumber,
            'voucher_id'    => $voucherId,
            'voucher_code'  => $voucherCode,
            'claimed_at'    => now()->toIso8601String(),
            'service'       => 'DPark-Membership-Service',
        ], $bearerToken);
    }
}
