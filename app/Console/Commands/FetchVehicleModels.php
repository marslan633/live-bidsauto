<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Manufacturer;
use App\Models\VehicleModel;

class FetchVehicleModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:vehicle-models';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch vehicle models for all manufacturers from the API and save them to the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching manufacturers from the database...');
        $manufacturers = Manufacturer::all();

        if ($manufacturers->isEmpty()) {
            $this->error('No manufacturers found in the database.');
            return 1;
        }

        foreach ($manufacturers as $manufacturer) {
            foreach (['cars', 'motorcycles'] as $type) {
                $url = "https://carstat.dev/api/models/{$manufacturer->manufacturer_api_id}/{$type}";

                $this->info("Fetching models for Manufacturer ID: {$manufacturer->manufacturer_api_id}, Type: {$type}");

                $response = Http::withHeaders([
                    'x-api-key' => env('CAR_API_KEY'),
                ])->get($url);

          

                if ($response->failed()) {
                    $this->error("Failed to fetch models for Manufacturer ID: {$manufacturer->manufacturer_api_id}, Type: {$type}");
                    continue; // Skip to the next type
                }

                $models = $response->json()['data'];

                foreach ($models as $model) {
                    VehicleModel::updateOrCreate(
                        [
                            'vehicle_model_api_id' => $model['id'],
                        ],
                        [
                            'name' => $model['name'],
                            'cars_qty' => $model['cars_qty'],
                            'manufacturer_id' => $manufacturer->id, // Local DB ID
                            'generations_qty' => $model['generations_qty'],
                            'type' => $type, // Save the type (cars or motorcycles)
                        ]
                    );

                    $this->info("Saved/Updated Model: {$model['name']} (ID: {$model['id']})");
                }
            }
        }

        $this->info('Vehicle models for all manufacturers have been successfully fetched and saved.');
        return 0;
    }
}