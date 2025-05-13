<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;
use App\Models\VehicleRecord;

class StoreVehicleToElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vehicles;

    public function __construct($vehicles)
    {
        $this->queue = 'store_vehicle_records_to_elasticsearch_job';
        $this->vehicles = $vehicles;
    }

    public function handle()
    {
        $client = app('ElasticsearchKvmFour');
        $bulkData = [];

        foreach ($this->vehicles as $vehicle) {
            // Already eager loaded relationships, no need to reload them again
            Log::info('Indexing Vehicle', ['vehicle_id' => $vehicle->id]);

            // Prepare the bulk data for Elasticsearch
            $bulkData[] = [
                'index' => [
                    '_index' => 'vehicle_records',
                    '_id' => $vehicle->id,
                ]
            ];

            // Including all relationships in the vehicle data
            $vehicleData = $vehicle->toArray();
            $bulkData[] = $vehicleData;

            // Log the vehicle data being indexed
            Log::info('Preparing to index vehicle', ['vehicle_id' => $vehicle->id]);
        }

        // Ensure that there's data to index
        if (!empty($bulkData)) {
            try {
                // Debugging: Log the bulk data structure before sending it
                Log::info('Bulk Index Data: ', ['bulk_data' => json_encode($bulkData)]);

                // Execute the bulk request to Elasticsearch
                $response = $client->bulk(['body' => $bulkData]);

                // Debugging: Log the response from Elasticsearch
                Log::info('Bulk Indexing Response: ', ['response' => $response]);

                // Check if Elasticsearch returned errors
                if (isset($response['errors']) && $response['errors']) {
                    Log::error('Errors while indexing vehicles', ['errors' => $response['items']]);
                } else {
                    Log::info('Vehicles successfully indexed.');
                }
            } catch (\Exception $e) {
                Log::error('Error in Bulk Indexing: ', ['error' => $e->getMessage()]);
            }
        } else {
            Log::info('No vehicles found for indexing.');
        }
    }
}
