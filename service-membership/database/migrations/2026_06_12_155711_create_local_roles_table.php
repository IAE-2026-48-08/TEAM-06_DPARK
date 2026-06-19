<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pemetaan user SSO ke role lokal DPark Membership Service.
     *
     * Dibuat untuk mendukung Modul 1 (Federated SSO):
     * - Menyimpan sub (user identifier) dari JWT SSO Dosen
     * - Memetakan ke role lokal: admin, operator, atau member
     */
    public function up(): void
    {
        Schema::create('local_roles', function (Blueprint $table) {
            $table->id();
            $table->string('sso_sub')->unique()->comment('Subject identifier dari JWT SSO Dosen');
            $table->string('email')->nullable()->comment('Email user dari payload JWT');
            $table->string('sso_roles')->nullable()->comment('Role dari SSO Dosen (raw, comma-separated)');
            $table->string('local_role')->default('member')->comment('Role lokal: admin, operator, member');
            $table->text('jwt_payload')->nullable()->comment('Full payload JWT untuk debugging');
            $table->timestamp('last_seen')->nullable()->comment('Terakhir kali user login via SSO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_roles');
    }
};
