<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberVoucher extends Model
{
    use HasFactory;

    protected $table = 'member_vouchers';

    protected $fillable = [
        'member_id',
        'voucher_id',
        'claimed_at',
        'used_at',
        'transaction_id',
        'status',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
