<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Service Lahan & Lokasi (Service A) - DPark Bandung",
    version: "1.0.0",
    description: "API Documentation for Service Lahan & Lokasi (Service A) of DPark Bandung"
)]
#[OA\Server(
    url: "http://localhost:8000",
    description: "Local Development Server"
)]
#[OA\Server(
    url: "http://localhost",
    description: "Docker/Production Server"
)]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-API-KEY",
    description: "Enter API Key to access secured endpoints"
)]
abstract class Controller
{
    //
}
