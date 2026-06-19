<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoapAuditClient
{
    /**
     * The IAE SSO base URL.
     */
    private string $baseUrl;

    /**
     * The Team ID for SOAP audit requests.
     */
    private string $teamId;

    public function __construct()
    {
        $this->baseUrl = config('services.iae_sso.url', 'https://iae-sso.virtualfri.id');
        $this->teamId = config('services.iae_sso.team_id', 'TEAM-06');
    }

    /**
     * Send audit log to the Legacy SOAP/XML service.
     *
     * @param string $bearerToken  JWT token for authorization
     * @param string $activityName Name of the business activity (e.g., 'ParkirCheckIn')
     * @param array  $logData      Transaction data to be embedded as CDATA JSON
     * @return array               ['success' => bool, 'receipt_number' => string|null, 'raw_response' => string]
     */
    public function sendAudit(string $bearerToken, string $activityName, array $logData): array
    {
        $jsonContent = json_encode($logData, JSON_UNESCAPED_SLASHES);

        // Build SOAP XML Envelope
        $soapXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
  <soap:Body>
    <iae:AuditRequest>
      <iae:TeamID>{$this->teamId}</iae:TeamID>
      <iae:ActivityName>{$activityName}</iae:ActivityName>
      <iae:LogContent><![CDATA[{$jsonContent}]]></iae:LogContent>
    </iae:AuditRequest>
  </soap:Body>
</soap:Envelope>
XML;

        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $bearerToken,
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($soapXml, 'text/xml')
                ->post($this->baseUrl . '/soap/v1/audit');

            $rawResponse = $response->body();

            Log::info('SOAP Audit Response', [
                'status_code' => $response->status(),
                'body' => $rawResponse,
            ]);

            // Parse the XML response to extract ReceiptNumber
            $receiptNumber = $this->extractReceiptNumber($rawResponse);

            return [
                'success' => $receiptNumber !== null,
                'receipt_number' => $receiptNumber,
                'raw_response' => $rawResponse,
            ];

        } catch (\Exception $e) {
            Log::error('SOAP Audit failed: ' . $e->getMessage());
            return [
                'success' => false,
                'receipt_number' => null,
                'raw_response' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract the ReceiptNumber from the SOAP XML response.
     *
     * @param string $xmlString  Raw XML response from the SOAP service
     * @return string|null       The ReceiptNumber value, or null if not found
     */
    private function extractReceiptNumber(string $xmlString): ?string
    {
        try {
            // Remove namespace prefixes for simpler parsing
            $xmlString = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$2_$3', $xmlString);
            $xml = simplexml_load_string($xmlString);

            if ($xml === false) {
                return null;
            }

            // Navigate: Envelope -> Body -> AuditResponse -> ReceiptNumber
            $body = $xml->soap_Body ?? $xml->Body ?? null;
            if ($body === null) return null;

            $auditResponse = $body->iae_AuditResponse ?? $body->AuditResponse ?? null;
            if ($auditResponse === null) return null;

            $receiptNumber = $auditResponse->iae_ReceiptNumber ?? $auditResponse->ReceiptNumber ?? null;

            return $receiptNumber ? (string) $receiptNumber : null;

        } catch (\Exception $e) {
            Log::error('Failed to parse SOAP response XML: ' . $e->getMessage());
            return null;
        }
    }
}
