<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LandingPageRule;

class LandingPageRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LandingPageRule::truncate();

        /* -----------------------------------
         | Popular Vehicles
         |-----------------------------------*/
        LandingPageRule::create([
            'section_key' => 'popular',
            'section_title' => 'Popular Vehicles',
            'limit' => 4,
            'request_body' => [
                'data_source' => 'active',
                'year_from' => 2019,
                'year_to' => now()->year,
                'vehicle_types' => [1],          // Cars
                'damages' => [2,3,5,7,9,11],      // IDs ONLY
                'buy_now_sort' => true
            ]
        ]);


        /* -----------------------------------
         | Luxury Cars
         |-----------------------------------*/
        LandingPageRule::create([
            'section_key' => 'luxury',
            'section_title' => 'Luxury Cars',
            'limit' => 4,
            'request_body' => [
                'data_source' => 'active',
                'vehicle_types' => [1],          // Cars
                'buy_now_sort' => true
            ]
        ]);


        /* -----------------------------------
         | ATV
         |-----------------------------------*/
        LandingPageRule::create([
            'section_key' => 'atv',
            'section_title' => 'ATV',
            'limit' => 4,
            'request_body' => [
                'data_source' => 'active',
                'vehicle_types' => [6],          // ATV
                'damages' => [1,2,3,5,7,9,11],
                'buy_now_sort' => true
            ]
        ]);


        /* -----------------------------------
         | Motorcycle
         |-----------------------------------*/
        LandingPageRule::create([
            'section_key' => 'motorcycle',
            'section_title' => 'Motorcycle',
            'limit' => 4,
            'request_body' => [
                'data_source' => 'active',
                'year_from' => 2015,
                'vehicle_types' => [3],          // Motorcycle
                'damages' => [1,2,3,5,7,9,11],   
                'buy_now_sort' => true
            ]
        ]);


        /* -----------------------------------
         | Jet Ski / Snowmobile
         |-----------------------------------*/
        LandingPageRule::create([
            'section_key' => 'jet_ski_snowmobile',
            'section_title' => 'Jet Ski / Snowmobile',
            'limit' => 4,
            'request_body' => [
                'data_source' => 'active',
                'vehicle_types' => [10,11],        // Jet Ski + Watercraft
                'damages' => [1,2,3,5,7,9,11],
                'buy_now_sort' => true
            ]
        ]);
    }
}