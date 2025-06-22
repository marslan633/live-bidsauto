<?php

namespace App\Jobs\KvmFour;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;

class StoreVehicleArchivedToElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vehicles;

    /**
     * Create a new job instance.
     *
     * @param $vehicles
     * @return void
     */
    public function __construct($vehicles)
    {
        $this->queue = 'store_archived_vehicle_records_to_elasticsearch_job';
        $this->vehicles = $vehicles;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
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
            Log::info('Indexing Archived Vehicle', ['vehicle_id' => $vehicle->id]);

            // Including all relationships in the vehicle data
            $vehicleData = $vehicle->toArray();
            $vehicleData['vin'] = strtolower($vehicleData['vin']);
            /*
            // Bulk Insert Logic (Commented)
            $bulkData[] = [
                'index' => [
                    '_index' => 'vehicle_records',  // Different index for archived records
                    '_id' => $vehicle->id,
                ]
            ];

            // Add vehicle data to the bulk request
            $bulkData[] = $vehicleData;
            */

            // Single Document Insert Logic
            try {
                // Prepare the upsert parameters
                $params = [
                    'index' => 'vehicle_records',
                    'id'    => $vehicle->id,
                    'body'  => [
                        'script' => [
                            'source' => 'ctx._source.putAll(params.vehicleData)',
                            'params' => [
                                'vehicleData' => $vehicleData
                            ],
                        ],
                        'upsert' => $vehicleData,
                    ]
                ];

                // Perform the upsert operation
                $response = $client->update($params);

                Log::info('Upserted Vehicle in Elasticsearch', [
                    'vehicle_id' => $vehicle->id,
                    'response' => $response,
                ]);
            } catch (\Exception $e) {
                $clientKvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.4',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'store_archived_vehicle_records_to_elasticsearch_job',
                        'error' => 'Error Upserting Vehicle in Elasticsearch ' . json_encode($e->getMessage()),
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }



            // Log::info('Preparing to index archived vehicle', ['vehicle_id' => $vehicle->id]);
        }


    }
}
