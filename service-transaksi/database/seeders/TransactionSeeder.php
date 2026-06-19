<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $transactions = [
            [
                'plate_number'    => 'D 1234 ABC',
                'location_id'     => 1,
                'vehicle_type'    => 'mobil',
                'entry_time'      => Carbon::now()->subHours(3),
                'exit_time'       => Carbon::now()->subHours(1),
                'amount'          => 10000,
                'status'          => 'completed',
                'member_id'       => 'MBR-001',
                'discount_amount' => 2000,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'plate_number'    => 'B 5678 XYZ',
                'location_id'     => 1,
                'vehicle_type'    => 'motor',
                'entry_time'      => Carbon::now()->subHours(2),
                'exit_time'       => Carbon::now()->subMinutes(30),
                'amount'          => 4000,
                'status'          => 'completed',
                'member_id'       => null,
                'discount_amount' => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'plate_number'    => 'F 9999 ZZZ',
                'location_id'     => 2,
                'vehicle_type'    => 'mobil',
                'entry_time'      => Carbon::now()->subHour(),
                'exit_time'       => null,
                'amount'          => null,
                'status'          => 'ongoing',
                'member_id'       => 'MBR-002',
                'discount_amount' => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'plate_number'    => 'D 4321 DEF',
                'location_id'     => 2,
                'vehicle_type'    => 'motor',
                'entry_time'      => Carbon::now()->subMinutes(45),
                'exit_time'       => null,
                'amount'          => null,
                'status'          => 'ongoing',
                'member_id'       => null,
                'discount_amount' => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'plate_number'    => 'Z 1111 GHI',
                'location_id'     => 3,
                'vehicle_type'    => 'mobil',
                'entry_time'      => Carbon::now()->subHours(5),
                'exit_time'       => Carbon::now()->subHours(2),
                'amount'          => 15000,
                'status'          => 'completed',
                'member_id'       => 'MBR-003',
                'discount_amount' => 5000,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ];

        DB::table('transactions')->insert($transactions);
    }
}