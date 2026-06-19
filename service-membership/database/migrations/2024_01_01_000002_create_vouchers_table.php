<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();               // kode unik voucher, mis: DISC20
            $table->string('name');                         // nama voucher
            $table->text('description')->nullable();        // deskripsi
            $table->enum('discount_type', ['percentage', 'fixed']); // tipe diskon
            $table->decimal('discount_value', 10, 2);      // nilai diskon
            $table->integer('min_duration_hours')->default(1); // min jam parkir
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // maks potongan
            $table->integer('total_quota');                 // total kuota
            $table->integer('claimed_count')->default(0);  // sudah diklaim
            $table->date('valid_from');                     // mulai berlaku
            $table->date('valid_until');                    // berakhir
            $table->boolean('is_active')->default(true);   // status aktif
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
