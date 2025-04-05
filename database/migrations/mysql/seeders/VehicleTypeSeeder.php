<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\VehicleType;

class VehicleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicleTypes = [
            ['name' => 'automobile', 'vehicle_type_api_id' => 1],
            ['name' => 'motorcycle', 'vehicle_type_api_id' => 2],
            ['name' => 'trailers', 'vehicle_type_api_id' => 3],
            ['name' => 'truck', 'vehicle_type_api_id' => 4],
            ['name' => 'atv', 'vehicle_type_api_id' => 5],
            ['name' => 'bus', 'vehicle_type_api_id' => 8],
            ['name' => 'industrial_equipment', 'vehicle_type_api_id' => 9],
            ['name' => 'mobile_home', 'vehicle_type_api_id' => 10],
            ['name' => 'jet_sky', 'vehicle_type_api_id' => 11],
            ['name' => 'watercraft', 'vehicle_type_api_id' => 12],
            ['name' => 'boat', 'vehicle_type_api_id' => 7],
            ['name' => 'emergency_equipment', 'vehicle_type_api_id' => 13],
        ];

        foreach ($vehicleTypes as $vehicleType) {
            VehicleType::updateOrCreate(['vehicle_type_api_id' => $vehicleType['vehicle_type_api_id']], $vehicleType);
        }
    }
}