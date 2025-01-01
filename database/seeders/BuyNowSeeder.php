<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BuyNow;

class BuyNowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyNows = [
            ['name' => 'buyNowWithPrice'],
            ['name' => 'buyNowWithoutPrice'],
        ];

        foreach ($buyNows as $buyNow) {
            BuyNow::updateOrCreate(
                ['name' => $buyNow['name']],
                $buyNow
            );
        }
    }
}