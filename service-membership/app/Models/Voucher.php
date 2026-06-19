<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_duration_hours',
        'max_discount_amount',
        'total_quota',
        'claimed_count',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'valid_from'          => 'date',
        'valid_until'         => 'date',
        'is_active'           => 'boolean',
        'discount_value'      => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
    ];

    /**
     * Relasi: Voucher diklaim oleh banyak member.
     */
    public function memberVouchers()
    {
        return $this->hasMany(MemberVoucher::class);
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'member_vouchers')
                    ->withPivot(['claimed_at', 'used_at', 'transaction_id', 'status'])
                    ->withTimestamps();
    }

    /**
     * Cek apakah voucher masih bisa diklaim.
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) return false;
        if ($this->valid_until->isPast()) return false;
        if ($this->claimed_count >= $this->total_quota) return false;
        return true;
    }

    /**
     * Hitung nilai diskon berdasarkan subtotal parkir.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'percentage') {
            $discount = $subtotal * ($this->discount_value / 100);
            if ($this->max_discount_amount !== null) {
                $discount = min($discount, (float) $this->max_discount_amount);
            }
            return $discount;
        }

        // fixed: potongan langsung
        return min((float) $this->discount_value, $subtotal);
    }

    /**
     * Sisa kuota voucher (untuk response API/GraphQL).
     */
    public function getRemainingQuotaAttribute(): int
    {
        return $this->total_quota - $this->claimed_count;
    }
}
