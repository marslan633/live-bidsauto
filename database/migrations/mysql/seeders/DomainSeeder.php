<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Domain;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $domains = [
            ['name' => 'iaai_com', 'domain_api_id' => 1],
            ['name' => 'copart_com', 'domain_api_id' => 3],
            ['name' => 'encar_com', 'domain_api_id' => 12],
        ];

        foreach ($domains as $domain) {
            Domain::updateOrCreate(['domain_api_id' => $domain['domain_api_id']], $domain);
        }
    }
}