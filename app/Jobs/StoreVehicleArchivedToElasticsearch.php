<?php

namespace App\Jobs;

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

            $bulkData[] = [
                'update' => [
                    '_index' => 'vehicle_record_archiveds',
                    '_id' => $vehicle->id,
                ]
            ];

            $bulkData[] = [
                'doc' => $vehicleData,
                'doc_as_upsert' => true
            ];

            // $bulkData[] = [
            //     'index' => [
            //         '_index' => 'vehicle_record_archiveds',  // Different index for archived records
            //         '_id' => $vehicle->id,
            //     ]
            // ];

            // // Add vehicle data to the bulk request
            // $bulkData[] = $vehicleData;

            Log::info('Preparing to index archived vehicle', ['vehicle_id' => $vehicle->id]);
        }

        // Ensure that there's data to index
        if (!empty($bulkData)) {
            try {
                // Debugging: Log the bulk data structure before sending it
                Log::info('Bulk Index Data (Archived): ', ['bulk_data' => json_encode($bulkData)]);

                // Execute the bulk request to Elasticsearch
                $response = $client->bulk(['body' => $bulkData]);

                // Debugging: Log the response from Elasticsearch
                Log::info('Bulk Indexing Response (Archived): ', ['response' => $response]);

                // Check if Elasticsearch returned errors
                if (isset($response['errors']) && $response['errors']) {
                    Log::error('Errors while indexing archived vehicles', ['errors' => $response['items']]);
                } else {
                    Log::info('Archived vehicles successfully indexed.');
                }
            } catch (\Exception $e) {
                Log::error('Error in Bulk Indexing Archived Vehicles: ', ['error' => $e->getMessage()]);
            }
        } else {
            Log::info('No archived vehicles found for indexing.');
        }
    }
}
