<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'not_checked', 'status_api_id' => 1],
            ['name' => 'not_on_sale', 'status_api_id' => 2],
            ['name' => 'sale', 'status_api_id' => 3],
            ['name' => 'on_approval', 'status_api_id' => 4],
            ['name' => 'new_auction', 'status_api_id' => 5],
            ['name' => 'sold', 'status_api_id' => 6],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(['status_api_id' => $status['status_api_id']], $status);
        }
    }
}