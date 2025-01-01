<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\BodyTypeSeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\TransmissionSeeder;
use Database\Seeders\DriveWheelSeeder;
use Database\Seeders\FuelSeeder;
use Database\Seeders\ConditionSeeder;
use Database\Seeders\StatusSeeder;
use Database\Seeders\VehicleTypeSeeder;
use Database\Seeders\DomainSeeder;
use Database\Seeders\YearSeeder;
use Database\Seeders\BuyNowSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(BodyTypeSeeder::class);
        $this->call(ColorSeeder::class);
        $this->call(TransmissionSeeder::class);
        $this->call(DriveWheelSeeder::class);
        $this->call(FuelSeeder::class);
        $this->call(ConditionSeeder::class);
        $this->call(StatusSeeder::class);
        $this->call(VehicleTypeSeeder::class);
        $this->call(DomainSeeder::class);
        $this->call(YearSeeder::class);
        $this->call(BuyNowSeeder::class);
    }
}