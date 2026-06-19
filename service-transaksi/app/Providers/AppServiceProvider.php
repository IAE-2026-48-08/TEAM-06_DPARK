<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Transaksi Parkir',
    version: '1.0.0',
    description: 'API Transaksi untuk web DPark'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local Development Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'ApiKeyAuth',
    type: 'apiKey',
    in: 'header',
    name: 'X-IAE-KEY',
    description: 'Input API Key here.'
)]
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {}
}
