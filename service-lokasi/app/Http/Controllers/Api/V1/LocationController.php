<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Location;
use App\Services\AmqpPublisher;
use App\Services\SoapAuditClient;
use App\Services\SsoTokenManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class LocationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/locations',
        summary: 'Get all parking locations with real-time empty slots',
        tags: ['Locations'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'DPARK Braga'),
                            new OA\Property(property: 'address', type: 'string', example: 'Jl. Braga No. 5 Bandung'),
                            new OA\Property(property: 'capacity_car', type: 'integer', example: 40),
                            new OA\Property(property: 'capacity_motor', type: 'integer', example: 80),
                            new OA\Property(property: 'occupied_car', type: 'integer', example: 32),
                            new OA\Property(property: 'occupied_motor', type: 'integer', example: 45),
                            new OA\Property(property: 'available_car_slots', type: 'integer', example: 8),
                            new OA\Property(property: 'available_motor_slots', type: 'integer', example: 35),
                            new OA\Property(property: 'tariff_car', type: 'string', example: '5000.00'),
                            new OA\Property(property: 'tariff_motor', type: 'string', example: '2000.00'),
                            new OA\Property(property: 'operating_hours', type: 'string', example: '06:00 - 23:00')
                        ]
                    )
                )
            )
        ]
    )]
    public function index()
    {
        $locations = Location::all();
        return response()->json([
            'success' => true,
            'data' => $locations
        ], 200);
    }

    #[OA\Get(
        path: '/api/v1/locations/{id}',
        summary: 'Get specific location details by ID',
        tags: ['Locations'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'Location ID',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'DPARK Braga'),
                        new OA\Property(property: 'address', type: 'string', example: 'Jl. Braga No. 5 Bandung'),
                        new OA\Property(property: 'capacity_car', type: 'integer', example: 40),
                        new OA\Property(property: 'capacity_motor', type: 'integer', example: 80),
                        new OA\Property(property: 'occupied_car', type: 'integer', example: 32),
                        new OA\Property(property: 'occupied_motor', type: 'integer', example: 45),
                        new OA\Property(property: 'available_car_slots', type: 'integer', example: 8),
                        new OA\Property(property: 'available_motor_slots', type: 'integer', example: 35),
                        new OA\Property(property: 'tariff_car', type: 'string', example: '5000.00'),
                        new OA\Property(property: 'tariff_motor', type: 'string', example: '2000.00'),
                        new OA\Property(property: 'operating_hours', type: 'string', example: '06:00 - 23:00')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Location not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Location not found')
                    ]
                )
            )
        ]
    )]
    public function show($id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $location
        ], 200);
    }

    #[OA\Post(
        path: '/api/v1/locations',
        summary: 'Add a new parking location (Admin)',
        tags: ['Locations'],
        security: [['ApiKeyAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'address', 'capacity_car', 'capacity_motor', 'tariff_car', 'tariff_motor', 'operating_hours'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'DPARK Pasteur'),
                    new OA\Property(property: 'address', type: 'string', example: 'Jl. Pasteur No. 12 Bandung'),
                    new OA\Property(property: 'capacity_car', type: 'integer', example: 30),
                    new OA\Property(property: 'capacity_motor', type: 'integer', example: 60),
                    new OA\Property(property: 'tariff_car', type: 'number', format: 'float', example: 5000.00),
                    new OA\Property(property: 'tariff_motor', type: 'number', format: 'float', example: 2000.00),
                    new OA\Property(property: 'operating_hours', type: 'string', example: '06:00 - 23:00')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Location created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation errors occurred'),
                        new OA\Property(property: 'errors', type: 'object')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized: Invalid or missing X-API-KEY')
                    ]
                )
            )
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'capacity_car' => 'required|integer|min:0',
            'capacity_motor' => 'required|integer|min:0',
            'tariff_car' => 'required|numeric|min:0',
            'tariff_motor' => 'required|numeric|min:0',
            'operating_hours' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors()
            ], 400);
        }

        $location = Location::create([
            'name' => $request->name,
            'address' => $request->address,
            'capacity_car' => $request->capacity_car,
            'capacity_motor' => $request->capacity_motor,
            'occupied_car' => 0,
            'occupied_motor' => 0,
            'tariff_car' => $request->tariff_car,
            'tariff_motor' => $request->tariff_motor,
            'operating_hours' => $request->operating_hours,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location added successfully',
            'data' => $location
        ], 201);
    }

    /**
     * Check-in a vehicle at a parking location.
     *
     * This is a CRITICAL STATE-CHANGING transaction that:
     * 1. Validates JWT from Federated SSO (Cloud Dosen)
     * 2. Increments occupied vehicle count (state change)
     * 3. Sends audit log to Legacy SOAP/XML service and stores ReceiptNumber
     * 4. Publishes event notification to RabbitMQ via AMQP
     */
    #[OA\Post(
        path: '/api/v1/locations/{id}/check-in',
        summary: 'Check-in a vehicle at a parking location (SSO Protected)',
        tags: ['Locations'],
        security: [['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'Location ID',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['vehicle_type', 'license_plate'],
                properties: [
                    new OA\Property(property: 'vehicle_type', type: 'string', enum: ['car', 'motor'], example: 'car'),
                    new OA\Property(property: 'license_plate', type: 'string', example: 'D 1234 AB')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Vehicle checked in successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Vehicle checked in successfully'),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'audit', type: 'object'),
                        new OA\Property(property: 'amqp', type: 'object')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation or capacity error'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Location not found')
        ]
    )]
    public function checkIn(Request $request, $id)
    {
        // 1. Validate input
        $validator = Validator::make($request->all(), [
            'vehicle_type' => 'required|string|in:car,motor',
            'license_plate' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors()
            ], 400);
        }

        // 2. Find location
        $location = Location::find($id);
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        // 3. Check capacity and increment occupied count (STATE-CHANGING)
        $vehicleType = $request->vehicle_type;
        $occupiedField = 'occupied_' . $vehicleType;
        $capacityField = 'capacity_' . $vehicleType;

        if ($location->$occupiedField >= $location->$capacityField) {
            return response()->json([
                'success' => false,
                'message' => 'No available slots for ' . $vehicleType . ' at this location'
            ], 400);
        }

        $location->increment($occupiedField);
        $location->refresh();

        // 4. Fetch/Retrieve M2M token for SOAP and AMQP services
        try {
            $m2mToken = SsoTokenManager::getM2mToken();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to get M2M Token: ' . $e->getMessage());
            $m2mToken = $request->input('jwt_token', ''); // fallback
        }

        // 5. Send SOAP Audit to Legacy System
        $soapClient = new SoapAuditClient();
        $auditData = [
            'location_id' => $location->id,
            'location_name' => $location->name,
            'vehicle_type' => $vehicleType,
            'license_plate' => $request->license_plate,
            'occupied_after' => $location->$occupiedField,
            'available_after' => $location->{$vehicleType === 'car' ? 'available_car_slots' : 'available_motor_slots'},
            'tariff' => $location->{'tariff_' . $vehicleType},
            'timestamp' => now()->toIso8601String(),
            'user' => Auth::user()?->email ?? 'unknown',
        ];

        $soapResult = $soapClient->sendAudit($m2mToken, 'ParkirCheckIn', $auditData);

        // 6. Store audit log with ReceiptNumber locally
        $auditLog = AuditLog::create([
            'user_id' => Auth::id(),
            'location_id' => $location->id,
            'vehicle_type' => $vehicleType,
            'license_plate' => $request->license_plate,
            'receipt_number' => $soapResult['receipt_number'],
            'status' => $soapResult['success'] ? 'SUCCESS' : 'FAILED',
        ]);

        // 7. Publish event to RabbitMQ via AMQP
        $amqpPublisher = new AmqpPublisher();
        $eventPayload = [
            'event' => 'parking.check_in',
            'team_id' => config('services.iae_sso.team_id'),
            'location_id' => $location->id,
            'location_name' => $location->name,
            'vehicle_type' => $vehicleType,
            'license_plate' => $request->license_plate,
            'receipt_number' => $soapResult['receipt_number'],
            'available_slots' => $location->{$vehicleType === 'car' ? 'available_car_slots' : 'available_motor_slots'},
            'warga_email' => Auth::user()?->email,
            'warga_name' => Auth::user()?->name,
            'timestamp' => now()->toIso8601String(),
        ];

        $amqpResult = $amqpPublisher->publish($m2mToken, 'lahan.checkin', $eventPayload);

        // 8. Return comprehensive response
        return response()->json([
            'success' => true,
            'message' => 'Vehicle checked in successfully',
            'data' => [
                'location' => $location,
                'audit_log' => $auditLog,
            ],
            'audit' => [
                'soap_success' => $soapResult['success'],
                'receipt_number' => $soapResult['receipt_number'],
            ],
            'amqp' => [
                'published' => $amqpResult['success'],
                'method' => $amqpResult['method'] ?? null,
            ],
            'user' => [
                'email' => Auth::user()?->email,
                'roles' => Auth::user()?->roles->pluck('name'),
            ],
        ], 200);
    }
}
