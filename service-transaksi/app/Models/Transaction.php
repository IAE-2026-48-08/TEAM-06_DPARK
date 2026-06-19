<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'plate_number',
        'location_id',
        'vehicle_type',
        'entry_time',
        'exit_time',
        'amount',
        'status',
        'member_id',
        'discount_amount',
        'receipt_number',
    ];
}