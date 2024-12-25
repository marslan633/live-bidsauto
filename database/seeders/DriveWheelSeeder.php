<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DriveWheel;

class DriveWheelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drive_wheels = [
            ['name' => 'rear', 'drive_wheel_api_id' => 1],
            ['name' => 'front', 'drive_wheel_api_id' => 2],
            ['name' => 'all', 'drive_wheel_api_id' => 3],
        ];

        foreach ($drive_wheels as $drive_wheel) {
            DriveWheel::updateOrCreate(['drive_wheel_api_id' => $drive_wheel['drive_wheel_api_id']], $drive_wheel);
        }
    }
}