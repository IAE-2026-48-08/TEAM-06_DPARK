<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Str;

class MembershipService
{
    /**
     * Generate nomor member unik format MBR-XXXX.
     */
    public function generateMemberNumber(): string
    {
        do {
            $number = 'MBR-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Member::where('member_number', $number)->exists());

        return $number;
    }

    /**
     * Ambil diskon otomatis berdasarkan tipe membership.
     */
    public function getDiscountByType(string $type): int
    {
        return Member::$discountMap[$type] ?? 10;
    }

    /**
     * Verifikasi membership dan kembalikan info diskon.
     * Dipanggil oleh Service B saat transaksi berlangsung.
     *
     * @return array{valid: bool, member: Member|null, discount_percentage: int, message: string}
     */
    public function verifyMembership(string $vehiclePlate): array
    {
        $member = Member::where('vehicle_plate', $vehiclePlate)->first();

        if (!$member) {
            return [
                'valid'               => false,
                'member'              => null,
                'discount_percentage' => 0,
                'message'             => 'Plat nomor tidak terdaftar sebagai member.',
            ];
        }

        if (!$member->isActive()) {
            return [
                'valid'               => false,
                'member'              => $member,
                'discount_percentage' => 0,
                'message'             => 'Membership tidak aktif atau sudah kadaluarsa.',
            ];
        }

        return [
            'valid'               => true,
            'member'              => $member,
            'discount_percentage' => $member->discount_percentage,
            'message'             => "Member aktif. Diskon {$member->discount_percentage}% akan diterapkan.",
        ];
    }

    /**
     * Hitung biaya parkir setelah diskon membership.
     */
    public function applyMembershipDiscount(float $subtotal, int $discountPercentage): array
    {
        $discountAmount = $subtotal * ($discountPercentage / 100);
        $finalAmount    = $subtotal - $discountAmount;

        return [
            'subtotal'            => $subtotal,
            'discount_percentage' => $discountPercentage,
            'discount_amount'     => $discountAmount,
            'final_amount'        => max(0, $finalAmount),
        ];
    }
}
