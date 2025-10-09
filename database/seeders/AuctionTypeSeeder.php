<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuctionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['auction_type_api_id' => 0, 'name' => 'unknown'],
            ['auction_type_api_id' => 1, 'name' => 'pure_sale'],
            ['auction_type_api_id' => 2, 'name' => 'minimum_bid'],
            ['auction_type_api_id' => 3, 'name' => 'on_approval'],
            ['auction_type_api_id' => 4, 'name' => 'live'],
            ['auction_type_api_id' => 5, 'name' => 'timed'],
        ];

        DB::table('auction_types')->insert($types);
    }
}