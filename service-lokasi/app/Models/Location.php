<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'name',
        'address',
        'capacity_car',
        'capacity_motor',
        'occupied_car',
        'occupied_motor',
        'tariff_car',
        'tariff_motor',
        'operating_hours',
    ];

    protected $appends = [
        'available_car_slots',
        'available_motor_slots',
    ];

    public function getAvailableCarSlotsAttribute(): int
    {
        return max(0, $this->capacity_car - $this->occupied_car);
    }

    public function getAvailableMotorSlotsAttribute(): int
    {
        return max(0, $this->capacity_motor - $this->occupied_motor);
    }
}
