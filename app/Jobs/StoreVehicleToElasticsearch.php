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
        $clientKvmOne = app('ElasticsearchKvmOne');
        $bulkData = [];

        // Eager load the relationships inside the job
        $this->vehicles->load([
            'manufacturer',
            'vehicleModel',
            'generation',
            'bodyType',
            'color',
            'engine',
            'transmission',
            'driveWheel',
            'vehicleType',
            'fuel',
            'status',
            'seller',
            'sellerType',
            'titleRelation',
            'detailedTitle',
            'damageMain',
            'damageSecond',
            'condition',
            'image',
            'country',
            'state',
            'city',
            'location',
            'sellingBranch',
            'buyNowRelation',
        ]);

        foreach ($this->vehicles as $vehicle) {
            // Prepare the bulk data for Elasticsearch
            Log::info('Indexing Vehicle', ['vehicle_id' => $vehicle->id]);

            // Including all relationships in the vehicle data
            $vehicleData = $vehicle->toArray();

            $bulkData[] = [
                'index' => [
                    '_index' => 'vehicle_records',
                    '_id' => $vehicle->id,
                ]
            ];

            // Add vehicle data to the bulk request
            $bulkData[] = $vehicleData;

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
                    $clientKvmOne->index([
                        'index' => 'error_logs',
                        'body' => [
                            'server_name' => 'KVM4.4',
                            'error_type' => 'Internal Server Error',
                            'command_name' => 'store_vehicle_records_to_elasticsearch_job',
                            'error' => 'Errors while indexing vehicles ' . json_encode($response['items']),
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                } else {
                    Log::info('Vehicles successfully indexed.');
                }
            } catch (\Exception $e) {
                $clientKvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.4',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'store_vehicle_records_to_elasticsearch_job',
                        'error' => 'Error in Bulk Indexing: ' . json_encode($e->getMessage()),
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }
        } else {
            $clientKvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.4',
                    'error_type' => 'General',
                    'command_name' => 'store_vehicle_records_to_elasticsearch_job',
                    'error' => 'No vehicles found for indexing.',
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }
}
