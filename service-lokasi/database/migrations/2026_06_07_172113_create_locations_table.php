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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->integer('capacity_car');
            $table->integer('capacity_motor');
            $table->integer('occupied_car')->default(0);
            $table->integer('occupied_motor')->default(0);
            $table->decimal('tariff_car', 10, 2);
            $table->decimal('tariff_motor', 10, 2);
            $table->string('operating_hours');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
