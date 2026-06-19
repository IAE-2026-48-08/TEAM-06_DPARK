<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_number')->unique(); // format: MBR-XXXX
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('vehicle_plate'); // plat nomor kendaraan
            $table->enum('membership_type', ['reguler', 'premium', 'vip'])->default('reguler');
            $table->integer('discount_percentage')->default(10); // 10%, 20%, 30%
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('expired_at')->nullable(); // null = tidak ada masa berlaku
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
