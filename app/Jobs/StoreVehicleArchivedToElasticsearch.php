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

            /*
            // Bulk Insert Logic (Commented)
            $bulkData[] = [
                'index' => [
                    '_index' => 'vehicle_record_archiveds',  // Different index for archived records
                    '_id' => $vehicle->id,
                ]
            ];

            // Add vehicle data to the bulk request
            $bulkData[] = $vehicleData;
            */

            // Single Document Insert Logic
            try {
                // Replace or create the document
                $response = $client->index([
                    'index' => 'vehicle_record_archiveds',
                    'id'    => $vehicle->id,
                    'body'  => $vehicleData,
                ]);

                Log::info('Replaced or Created Archived Vehicle in Elasticsearch', [
                    'vehicle_id' => $vehicle->id,
                    'response' => $response,
                ]);
            } catch (\Exception $e) {
                Log::error('Error Replacing or Creating Vehicle in Elasticsearch', [
                    'vehicle_id' => $vehicle->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }



            // Log::info('Preparing to index archived vehicle', ['vehicle_id' => $vehicle->id]);
        }


    }
}
