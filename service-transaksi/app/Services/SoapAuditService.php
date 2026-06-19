<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;

class SoapAuditService
{
    public function audit(array $transaction): ?string
    {
        $token = $this->getToken();

        if (!$token) {
            Log::error('[SOAP] Gagal mendapatkan token SSO');
            return null;
        }

        $this->mapTokenToLocalRole($token, $transaction['id']);

        // Langkah 2: Siapkan data transaksi sebagai JSON
        $logContent = json_encode([
            'transaction_id' => $transaction['id'],
            'plate_number'   => $transaction['plate_number'],
            'vehicle_type'   => $transaction['vehicle_type'],
            'location_id'    => $transaction['location_id'],
            'amount'         => $transaction['amount'],
            'status'         => $transaction['status'],
            'exit_time'      => $transaction['exit_time'],
        ]);

        // Langkah 3: Bangun XML sesuai format yang diminta dosen
        $teamId = env('TEAM_ID', 'TEAM-06');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:iae="http://iae.central/audit">
  <soap:Body>
    <iae:AuditRequest>
      <iae:TeamID>{$teamId}</iae:TeamID>
      <iae:ActivityName>ParkingCheckout</iae:ActivityName>
      <iae:LogContent><![CDATA[{$logContent}]]></iae:LogContent>
    </iae:AuditRequest>
  </soap:Body>
</soap:Envelope>
XML;

        // Langkah 4: Kirim ke endpoint SOAP Dosen
        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->withHeaders(['Content-Type' => 'text/xml; charset=utf-8'])
                ->withBody($xml, 'text/xml')
                ->post(env('SOAP_AUDIT_URL'));

            Log::info('[SOAP] Response: ' . $response->body());

            if (!$response->successful()) {
                Log::error('[SOAP] Gagal: HTTP ' . $response->status());
                return null;
            }

            // Langkah 5: Ambil ReceiptNumber dari response XML
            return $this->parseReceipt($response->body());
        } catch (\Exception $e) {
            Log::error('[SOAP] Exception: ' . $e->getMessage());
            return null;
        }
    }

    private function parseReceipt(string $xmlBody): ?string
    {
        // Hilangkan namespace prefix supaya SimpleXML bisa baca
        $cleaned = preg_replace('/(<\/?)(\w+):/', '$1', $xmlBody);
        $xml = @simplexml_load_string($cleaned);
        if (!$xml) return null;

        $results = $xml->xpath('//ReceiptNumber');
        return isset($results[0]) ? (string) $results[0] : null;
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

    private function mapTokenToLocalRole(string $token, int $transactionId): void
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) < 2) {
                return;
            }

            $payload = json_decode(base64_decode($parts[1]), true);

            $team = $payload['app']['team'] ?? null;
            $nim  = $payload['app']['nim'] ?? null;

            Transaction::where('id', $transactionId)->update([
                'team_id'          => $team,
                'processed_by_nim' => $nim,
            ]);

            Log::info("[SSO] Mapped JWT -> team={$team}, nim={$nim} untuk transaction #{$transactionId}");
        } catch (\Exception $e) {
            Log::error('[SSO] Gagal mapping token ke role lokal: ' . $e->getMessage());
        }
    }
}
