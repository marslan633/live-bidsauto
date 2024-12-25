<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Transmission;

class TransmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $transmissions = [
            ['name' => 'automatic', 'transmission_api_id' => 1],
            ['name' => 'manual', 'transmission_api_id' => 2],
        ];

        foreach ($transmissions as $transmission) {
            Transmission::updateOrCreate(['transmission_api_id' => $transmission['transmission_api_id']], $transmission);
        }
    }
}