<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_number',
        'name',
        'email',
        'phone',
        'vehicle_plate',
        'membership_type',
        'discount_percentage',
        'status',
        'joined_at',
        'expired_at',
    ];

    protected $casts = [
        'joined_at'  => 'datetime',
        'expired_at' => 'datetime',
    ];

    /**
     * Diskon otomatis berdasarkan tipe membership.
     */
    public static array $discountMap = [
        'reguler' => 10,
        'premium' => 20,
        'vip'     => 30,
    ];

    /**
     * Relasi: Member memiliki banyak voucher yang diklaim.
     */
    public function memberVouchers()
    {
        return $this->hasMany(MemberVoucher::class);
    }

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'member_vouchers')
                    ->withPivot(['claimed_at', 'used_at', 'transaction_id', 'status'])
                    ->withTimestamps();
    }

    /**
     * Cek apakah membership masih aktif dan belum expired.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->expired_at !== null && $this->expired_at->isPast()) {
            return false;
        }
        return true;
    }
}
