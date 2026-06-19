<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            [
                'member_number'       => 'MBR-0001',
                'name'                => 'Raka Pratama',
                'email'               => 'raka@example.com',
                'phone'               => '081234567890',
                'vehicle_plate'       => 'D 4321 RKA',
                'membership_type'     => 'premium',
                'discount_percentage' => 20,
                'status'              => 'active',
                'joined_at'           => now()->subMonths(6),
                'expired_at'          => now()->addMonths(6),
            ],
            [
                'member_number'       => 'MBR-0002',
                'name'                => 'Siti Nurhaliza',
                'email'               => 'siti@example.com',
                'phone'               => '082345678901',
                'vehicle_plate'       => 'B 1234 STI',
                'membership_type'     => 'vip',
                'discount_percentage' => 30,
                'status'              => 'active',
                'joined_at'           => now()->subYear(),
                'expired_at'          => now()->addYear(),
            ],
            [
                'member_number'       => 'MBR-0003',
                'name'                => 'Dinda Ayu',
                'email'               => 'dinda@example.com',
                'phone'               => '083456789012',
                'vehicle_plate'       => 'D 5678 DND',
                'membership_type'     => 'reguler',
                'discount_percentage' => 10,
                'status'              => 'active',
                'joined_at'           => now(),
                'expired_at'          => now()->addMonths(12),
            ],
            [
                'member_number'       => 'MBR-0004',
                'name'                => 'Budi Santoso',
                'email'               => 'budi@example.com',
                'phone'               => '084567890123',
                'vehicle_plate'       => 'D 9999 BSD',
                'membership_type'     => 'reguler',
                'discount_percentage' => 10,
                'status'              => 'inactive',
                'joined_at'           => now()->subYear(),
                'expired_at'          => now()->subMonth(), // sudah expired
            ],
        ];

        foreach ($members as $member) {
            Member::create($member);
        }

        $this->command->info('✅ MemberSeeder: ' . count($members) . ' member berhasil dibuat.');
    }
}
