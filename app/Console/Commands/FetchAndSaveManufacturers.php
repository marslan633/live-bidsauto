<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Manufacturer;

class FetchAndSaveManufacturers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:manufacturers';   

    /**
     * The console command description.
     *
     * @var string
     */
     protected $description = 'Fetch manufacturers from a third-party API and save them to the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        foreach (['cars', 'motorcycles'] as $type) {
            $url =  'https://carstat.dev/api/manufacturers/' . $type;

            $response = Http::withHeaders([
                'x-api-key' => env('CAR_API_KEY'),
            ])->get($url);

            if ($response->failed()) {
                $this->error("Failed to fetch data for type: {$type}");
                continue; // Skip to the next type
            }

            $manufacturers = $response->json()['data'];

            foreach ($manufacturers as $manufacturer) {
                // Debugging logs
                $this->info("Processing manufacturer ID: {$manufacturer['id']} | Name: {$manufacturer['name']} | Type: {$type}");

                Manufacturer::updateOrCreate(
                    [
                        'manufacturer_api_id' => $manufacturer['id'],
                        'type' => $type,
                    ],
                    [
                        'name' => $manufacturer['name'] ?? null,
                        'cars_qty' => $manufacturer['cars_qty'] ?? 0,
                        'image' => $manufacturer['image'] ?? null,
                        'models_qty' => $manufacturer['models_qty'] ?? 0,
                    ]
                );
            }
        }

        $this->info('Manufacturers for cars and motorcycles have been successfully fetched and saved.');
    }
}