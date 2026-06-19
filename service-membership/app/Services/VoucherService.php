<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberVoucher;
use App\Models\Voucher;
use Carbon\Carbon;

class VoucherService
{
    /**
     * Klaim voucher oleh member.
     * 1 member hanya bisa klaim 1 voucher yang sama.
     *
     * @return array{success: bool, message: string, data: mixed}
     */
    public function claimVoucher(int $memberId, int $voucherId): array
    {
        $member = Member::find($memberId);
        if (!$member || !$member->isActive()) {
            return [
                'success' => false,
                'message' => 'Member tidak ditemukan atau tidak aktif.',
                'data'    => null,
            ];
        }

        $voucher = Voucher::find($voucherId);
        if (!$voucher) {
            return [
                'success' => false,
                'message' => 'Voucher tidak ditemukan.',
                'data'    => null,
            ];
        }

        if (!$voucher->isAvailable()) {
            return [
                'success' => false,
                'message' => 'Voucher sudah tidak tersedia, kadaluarsa, atau habis kuota.',
                'data'    => null,
            ];
        }

        // Cek apakah member sudah pernah klaim voucher ini
        $alreadyClaimed = MemberVoucher::where('member_id', $memberId)
                                        ->where('voucher_id', $voucherId)
                                        ->exists();
        if ($alreadyClaimed) {
            return [
                'success' => false,
                'message' => 'Anda sudah pernah mengklaim voucher ini.',
                'data'    => null,
            ];
        }

        // Simpan klaim dan tambah claimed_count
        $memberVoucher = MemberVoucher::create([
            'member_id'  => $memberId,
            'voucher_id' => $voucherId,
            'claimed_at' => now(),
            'status'     => 'claimed',
        ]);

        $voucher->increment('claimed_count');

        return [
            'success' => true,
            'message' => 'Voucher berhasil diklaim.',
            'data'    => $memberVoucher->load(['voucher', 'member']),
        ];
    }

    /**
     * Validasi voucher saat transaksi parkir berlangsung.
     * Dipanggil oleh Service B.
     *
     * @return array{valid: bool, message: string, discount_type: string|null, discount_value: float, discount_amount: float, member_voucher_id: int|null}
     */
    public function validateVoucher(string $voucherCode, int $memberId, float $subtotal, float $durationHours): array
    {
        $voucher = Voucher::where('code', $voucherCode)->first();

        if (!$voucher) {
            return $this->invalidResponse('Kode voucher tidak ditemukan.');
        }

        // Cek voucher masih aktif dan belum expired
        if (!$voucher->is_active || $voucher->valid_until->isPast()) {
            return $this->invalidResponse('Voucher sudah tidak aktif atau kadaluarsa.');
        }

        // Cek minimum durasi parkir
        if ($durationHours < $voucher->min_duration_hours) {
            return $this->invalidResponse(
                "Voucher hanya berlaku untuk parkir minimal {$voucher->min_duration_hours} jam."
            );
        }

        // Cek apakah member memiliki voucher ini dan statusnya 'claimed'
        $memberVoucher = MemberVoucher::where('member_id', $memberId)
                                       ->where('voucher_id', $voucher->id)
                                       ->where('status', 'claimed')
                                       ->whereNull('used_at')
                                       ->first();

        if (!$memberVoucher) {
            return $this->invalidResponse('Voucher belum diklaim atau sudah digunakan oleh member ini.');
        }

        // Hitung diskon
        $discountAmount = $voucher->calculateDiscount($subtotal);

        return [
            'valid'            => true,
            'message'          => 'Voucher valid.',
            'discount_type'    => $voucher->discount_type,
            'discount_value'   => (float) $voucher->discount_value,
            'discount_amount'  => $discountAmount,
            'member_voucher_id'=> $memberVoucher->id,
            'voucher'          => $voucher,
        ];
    }

    /**
     * Tandai voucher sebagai sudah digunakan setelah transaksi selesai.
     */
    public function markVoucherAsUsed(int $memberVoucherId, string $transactionId): bool
    {
        $mv = MemberVoucher::find($memberVoucherId);
        if (!$mv) return false;

        $mv->update([
            'status'         => 'used',
            'used_at'        => now(),
            'transaction_id' => $transactionId,
        ]);

        return true;
    }

    private function invalidResponse(string $message): array
    {
        return [
            'valid'             => false,
            'message'           => $message,
            'discount_type'     => null,
            'discount_value'    => 0,
            'discount_amount'   => 0,
            'member_voucher_id' => null,
        ];
    }
}
