<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class YearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentTimestamp = Carbon::now();
        for ($year = 1900; $year <= 2026; $year++) {
            DB::table('years')->insert([
                'name' => $year,
                'created_at' => $currentTimestamp,
                'updated_at' => $currentTimestamp,
            ]);
        }
    }
}