<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Color;

class ColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $colors = [
            ['name' => 'silver', 'color_api_id' => 1],
            ['name' => 'purple', 'color_api_id' => 2],
            ['name' => 'orange', 'color_api_id' => 3],
            ['name' => 'green', 'color_api_id' => 4],
            ['name' => 'red', 'color_api_id' => 5],
            ['name' => 'gold', 'color_api_id' => 6],
            ['name' => 'charcoal', 'color_api_id' => 7],
            ['name' => 'brown', 'color_api_id' => 8],
            ['name' => 'grey', 'color_api_id' => 9],
            ['name' => 'turquoise', 'color_api_id' => 10],
            ['name' => 'blue', 'color_api_id' => 11],
            ['name' => 'bronze', 'color_api_id' => 12],
            ['name' => 'white', 'color_api_id' => 13],
            ['name' => 'cream', 'color_api_id' => 14],
            ['name' => 'black', 'color_api_id' => 15],
            ['name' => 'yellow', 'color_api_id' => 16],
            ['name' => 'beige', 'color_api_id' => 17],
            ['name' => 'pink', 'color_api_id' => 18],
            ['name' => 'two_colors', 'color_api_id' => 100],
        ];

        foreach ($colors as $color) {
            Color::updateOrCreate(['color_api_id' => $color['color_api_id']], $color);
        }
    }
}