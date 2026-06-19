<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $vouchers = [
            [
                'code'                => 'WELCOME10',
                'name'                => 'Welcome Discount 10%',
                'description'         => 'Voucher selamat datang untuk member baru. Diskon 10% untuk semua jenis kendaraan.',
                'discount_type'       => 'percentage',
                'discount_value'      => 10.00,
                'min_duration_hours'  => 1,
                'max_discount_amount' => 5000.00,
                'total_quota'         => 100,
                'claimed_count'       => 0,
                'valid_from'          => now()->toDateString(),
                'valid_until'         => now()->addMonths(3)->toDateString(),
                'is_active'           => true,
            ],
            [
                'code'                => 'DISC20',
                'name'                => 'Diskon 20% Weekend',
                'description'         => 'Nikmati diskon 20% parkir di akhir pekan. Berlaku untuk semua gedung ParkSmart.',
                'discount_type'       => 'percentage',
                'discount_value'      => 20.00,
                'min_duration_hours'  => 2,
                'max_discount_amount' => 10000.00,
                'total_quota'         => 50,
                'claimed_count'       => 0,
                'valid_from'          => now()->toDateString(),
                'valid_until'         => now()->addMonth()->toDateString(),
                'is_active'           => true,
            ],
            [
                'code'                => 'FLAT5000',
                'name'                => 'Potongan Flat Rp5.000',
                'description'         => 'Potongan langsung Rp5.000 untuk parkir minimal 3 jam.',
                'discount_type'       => 'fixed',
                'discount_value'      => 5000.00,
                'min_duration_hours'  => 3,
                'max_discount_amount' => null,
                'total_quota'         => 200,
                'claimed_count'       => 0,
                'valid_from'          => now()->toDateString(),
                'valid_until'         => now()->addMonths(6)->toDateString(),
                'is_active'           => true,
            ],
            [
                'code'                => 'VIP30',
                'name'                => 'VIP Exclusive 30%',
                'description'         => 'Voucher eksklusif khusus member VIP. Diskon 30% tanpa batas maksimal potongan.',
                'discount_type'       => 'percentage',
                'discount_value'      => 30.00,
                'min_duration_hours'  => 1,
                'max_discount_amount' => null,
                'total_quota'         => 30,
                'claimed_count'       => 0,
                'valid_from'          => now()->toDateString(),
                'valid_until'         => now()->addMonths(2)->toDateString(),
                'is_active'           => true,
            ],
            [
                'code'                => 'EXPIRED_TEST',
                'name'                => 'Voucher Kadaluarsa (Test)',
                'description'         => 'Voucher ini sudah expired — digunakan untuk testing validasi.',
                'discount_type'       => 'percentage',
                'discount_value'      => 15.00,
                'min_duration_hours'  => 1,
                'max_discount_amount' => null,
                'total_quota'         => 10,
                'claimed_count'       => 0,
                'valid_from'          => now()->subMonths(3)->toDateString(),
                'valid_until'         => now()->subDay()->toDateString(), // sudah expired
                'is_active'           => true,
            ],
        ];

        foreach ($vouchers as $voucher) {
            Voucher::create($voucher);
        }

        $this->command->info('✅ VoucherSeeder: ' . count($vouchers) . ' voucher berhasil dibuat.');
    }
}
