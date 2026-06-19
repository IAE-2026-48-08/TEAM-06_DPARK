<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('voucher_id')->constrained('vouchers')->onDelete('cascade');
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamp('used_at')->nullable();
            $table->string('transaction_id')->nullable(); // ID transaksi dari Service B
            $table->enum('status', ['claimed', 'used', 'expired'])->default('claimed');
            $table->timestamps();

            // 1 member hanya bisa klaim 1 voucher yang sama
            $table->unique(['member_id', 'voucher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_vouchers');
    }
};
