<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AmqpPublisher
{
    /**
     * The IAE SSO base URL (used for HTTP publish gateway).
     */
    private string $baseUrl;

    /**
     * The AMQP host for direct connection fallback.
     */
    private string $amqpHost;

    /**
     * The AMQP port.
     */
    private int $amqpPort;

    /**
     * The target exchange name.
     */
    private string $exchange;

    public function __construct()
    {
        $this->baseUrl = config('services.iae_sso.url', 'https://iae-sso.virtualfri.id');
        $this->amqpHost = config('services.iae_sso.amqp_host', 'iae-sso.virtualfri.id');
        $this->amqpPort = (int) config('services.iae_sso.amqp_port', 5672);
        $this->exchange = 'iae.central.exchange';
    }

    /**
     * Publish a JSON event notification to RabbitMQ.
     *
     * Primary: HTTP REST publish gateway at /api/v1/messages/publish
     * Fallback: Direct AMQP connection to the broker
     *
     * @param string $bearerToken  JWT token for authorization
     * @param string $routingKey   Routing key for the message (e.g., 'lahan.checkin')
     * @param array  $messageData  JSON-serializable event payload
     * @return array               ['success' => bool, 'method' => string, 'details' => mixed]
     */
    public function publish(string $bearerToken, string $routingKey, array $messageData): array
    {
        // Primary: HTTP REST publish gateway
        try {
            $result = $this->publishViaHttp($bearerToken, $routingKey, $messageData);
            if ($result['success']) {
                return $result;
            }
        } catch (\Exception $e) {
            Log::warning('HTTP AMQP publish failed, trying direct AMQP: ' . $e->getMessage());
        }

        // Fallback: Direct AMQP connection
        try {
            return $this->publishViaAmqp($routingKey, $messageData);
        } catch (\Exception $e) {
            Log::error('Direct AMQP publish also failed: ' . $e->getMessage());
            return [
                'success' => false,
                'method' => 'none',
                'details' => 'Both HTTP and AMQP publish methods failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Publish via the HTTP REST gateway endpoint.
     */
    private function publishViaHttp(string $bearerToken, string $routingKey, array $messageData): array
    {
        $response = Http::withOptions(['verify' => false])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $bearerToken,
            ])
            ->post($this->baseUrl . '/api/v1/messages/publish', [
                'routing_key' => $routingKey,
                'message' => $messageData,
            ]);

        $body = $response->json();

        Log::info('AMQP HTTP Publish Response', [
            'status_code' => $response->status(),
            'body' => $body,
        ]);

        $success = ($body['status'] ?? '') === 'success';

        return [
            'success' => $success,
            'method' => 'http',
            'details' => $body,
        ];
    }

    /**
     * Publish via direct AMQP connection to the RabbitMQ broker.
     */
    private function publishViaAmqp(string $routingKey, array $messageData): array
    {
        $connection = new AMQPStreamConnection(
            $this->amqpHost,
            $this->amqpPort,
            'guest',
            'guest',
            '/',
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0  // connection timeout
        );

        $channel = $connection->channel();

        $msg = new AMQPMessage(
            json_encode($messageData, JSON_UNESCAPED_SLASHES),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]
        );

        $channel->basic_publish($msg, $this->exchange, $routingKey);

        Log::info('AMQP Direct Publish Success', [
            'exchange' => $this->exchange,
            'routing_key' => $routingKey,
        ]);

        $channel->close();
        $connection->close();

        return [
            'success' => true,
            'method' => 'amqp_direct',
            'details' => [
                'exchange' => $this->exchange,
                'routing_key' => $routingKey,
            ],
        ];
    }
}
