<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BodyType;

class BodyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bodyTypes = [
            ['name' => 'sedan', 'body_type_api_id' => 1],
            ['name' => 'wagon', 'body_type_api_id' => 2],
            ['name' => 'coupe', 'body_type_api_id' => 3],
            ['name' => 'pickup', 'body_type_api_id' => 4],
            ['name' => 'SUV', 'body_type_api_id' => 5],
            ['name' => 'cabrio', 'body_type_api_id' => 6],
            ['name' => 'VAN', 'body_type_api_id' => 7],
            ['name' => 'moto', 'body_type_api_id' => 8],
            ['name' => 'furgon', 'body_type_api_id' => 9],
            ['name' => 'combi', 'body_type_api_id' => 10],
            ['name' => 'hatchback', 'body_type_api_id' => 11],
            ['name' => 'roadster', 'body_type_api_id' => 12],
            ['name' => 'limousine', 'body_type_api_id' => 13],
            ['name' => 'truck', 'body_type_api_id' => 14],
            ['name' => 'bike', 'body_type_api_id' => 15],
            ['name' => 'sport bike', 'body_type_api_id' => 16],
            ['name' => 'roadster bike', 'body_type_api_id' => 17],
            ['name' => 'industrial', 'body_type_api_id' => 18],
            ['name' => 'bus', 'body_type_api_id' => 19],
            ['name' => 'liftback', 'body_type_api_id' => 20],
            ['name' => 'enduro bike', 'body_type_api_id' => 21],
            ['name' => 'hearse', 'body_type_api_id' => 22],
            ['name' => 'fire truck', 'body_type_api_id' => 23],
            ['name' => 'trailer', 'body_type_api_id' => 24],
            ['name' => 'tandem', 'body_type_api_id' => 25],
            ['name' => 'garbage', 'body_type_api_id' => 26],
            ['name' => 'other', 'body_type_api_id' => 100],
        ];

        foreach ($bodyTypes as $bodyType) {
            BodyType::updateOrCreate(['body_type_api_id' => $bodyType['body_type_api_id']], $bodyType);
        }
    }
}