<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "Dokumentasi API untuk Service C: Membership & Voucher pada sistem DPark Bandung.",
    title: "DPark Membership Service API"
)]
#[OA\Server(
    url: "/api/v1",
    description: "DPark API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "x-api-key"
)]
abstract class Controller
{
    //
}
