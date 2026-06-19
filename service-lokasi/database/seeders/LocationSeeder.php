<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Location::create([
            'name' => 'DPARK Dago',
            'address' => 'Jl. Ir. H. Juanda No. 100 Bandung',
            'capacity_car' => 20,
            'capacity_motor' => 50,
            'occupied_car' => 20, // Full
            'occupied_motor' => 15,
            'tariff_car' => 5000.00,
            'tariff_motor' => 2000.00,
            'operating_hours' => '06:00 - 23:00',
        ]);

        \App\Models\Location::create([
            'name' => 'DPARK BIP',
            'address' => 'Jl. Merdeka No. 56 Bandung',
            'capacity_car' => 50,
            'capacity_motor' => 100,
            'occupied_car' => 10, // 40 available
            'occupied_motor' => 30,
            'tariff_car' => 5000.00,
            'tariff_motor' => 2000.00,
            'operating_hours' => '06:00 - 23:00',
        ]);

        \App\Models\Location::create([
            'name' => 'DPARK Braga',
            'address' => 'Jl. Braga No. 5 Bandung',
            'capacity_car' => 40,
            'capacity_motor' => 80,
            'occupied_car' => 32, // 8 available
            'occupied_motor' => 45,
            'tariff_car' => 5000.00,
            'tariff_motor' => 2000.00,
            'operating_hours' => '06:00 - 23:00',
        ]);
    }
}
