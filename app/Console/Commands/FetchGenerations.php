<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\VehicleModel;
use App\Models\Generation;

class FetchGenerations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:generations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch generations for all models and save them to the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching models from the database...');
        $models = VehicleModel::all();

        if ($models->isEmpty()) {
            $this->error('No models found in the database.');
            return 1;
        }

        foreach ($models as $model) {
            foreach (['cars', 'motorcycles'] as $type) {
                $url = "https://carstat.dev/api/generations/{$model->vehicle_model_api_id}/{$type}";

                $this->info("Fetching generations for Model ID: {$model->vehicle_model_api_id}, Type: {$type}");

                $response = Http::withHeaders([
                    'x-api-key' => env('CAR_API_KEY'),
                ])->get($url);

                if ($response->failed()) {
                    $this->error("Failed to fetch generations for Model ID: {$model->vehicle_model_api_id}, Type: {$type}");
                    continue; // Skip to the next type
                }

                $generations = $response->json()['data'];

                foreach ($generations as $generation) {
                    Generation::updateOrCreate(
                        [
                            'generation_api_id' => $generation['id'],
                        ],
                        [
                            'name' => $generation['name'] ?? null,
                            'cars_qty' => $generation['cars_qty'] ?? 0,
                            'from_year' => $generation['from_year'] ?? null,
                            'to_year' => $generation['to_year'] ?? null,
                            'manufacturer_id' => $model->manufacturer_id,
                            'model_id' => $model->id,
                            'type' => $type,
                        ]
                    );

                    $this->info("Saved/Updated Generation: {$generation['name']} (ID: {$generation['id']})");
                }
            }
        }

        $this->info('Generations for all models have been successfully fetched and saved.');
        return 0;
    }
}