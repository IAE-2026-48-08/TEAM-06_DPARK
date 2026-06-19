<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number');
            $table->unsignedBigInteger('location_id');
            $table->enum('vehicle_type', ['motor', 'mobil']);
            $table->dateTime('entry_time');
            $table->dateTime('exit_time')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->enum('status', ['ongoing', 'completed', 'cancelled'])->default('ongoing');
            $table->string('member_id')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('transactions');
    }
};