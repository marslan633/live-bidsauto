<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Condition;

class ConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conditions = [
            ['name' => 'run_and_drives', 'condition_api_id' => 0],
            ['name' => 'for_repair', 'condition_api_id' => 1],
            ['name' => 'to_be_dismantled', 'condition_api_id' => 2],
            ['name' => 'not_run', 'condition_api_id' => 3],
            ['name' => 'used', 'condition_api_id' => 4],
            ['name' => 'unconfirmed', 'condition_api_id' => 5],
            ['name' => 'engine_starts', 'condition_api_id' => 6],
            ['name' => 'enhanced', 'condition_api_id' => 7],
        ];

        foreach ($conditions as $condition) {
            Condition::updateOrCreate(['condition_api_id' => $condition['condition_api_id']], $condition);
        }
    }
}