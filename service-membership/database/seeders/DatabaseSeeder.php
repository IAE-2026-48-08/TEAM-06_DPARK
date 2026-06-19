<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * DPark Bandung — Service C: Membership & Voucher
     */
    public function run(): void
    {
        $this->call([
            MemberSeeder::class,
            VoucherSeeder::class,
        ]);
    }
}
