<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessKeysTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate a random API key
        $key = Str::random(40);

        DB::table('access_keys')->insert([
            'key' => $key,
            'name' => 'Angular SPA',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}