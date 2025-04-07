<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Fuel;

class FuelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fuels = [
            ['name' => 'diesel', 'fuel_api_id' => 1],
            ['name' => 'electric', 'fuel_api_id' => 2],
            ['name' => 'hybrid', 'fuel_api_id' => 3],
            ['name' => 'gasoline', 'fuel_api_id' => 4],
            ['name' => 'gas', 'fuel_api_id' => 5],
            ['name' => 'flexible', 'fuel_api_id' => 6],
            ['name' => 'hydrogen', 'fuel_api_id' => 7],
        ];

        foreach ($fuels as $fuel) {
            Fuel::updateOrCreate(['fuel_api_id' => $fuel['fuel_api_id']], $fuel);
        }
    }
}