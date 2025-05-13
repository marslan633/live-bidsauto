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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($vehicles)
    {
        $this->queue = 'store_vehicle_records_to_elasticsearch_job';
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
        foreach ($this->vehicles as $vehicle) {
            // Eager load all relationships to avoid N+1 problem
            $vehicle = $vehicle->load([
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
            Log::info('Vehicle', ['vehicle' => $vehicle]);
            // Prepare bulk data for indexing into both vehicle_records and index_vehicles
            // First index for 'vehicle_records'
            $bulkData[] = ['index' => ['_index' => 'vehicle_records', '_id' => $vehicle->id]];
            $bulkData[] = $vehicle->toArray();  // Index the vehicle and all of its relations

            // Log the vehicle data being indexed
            Log::info('Preparing to index vehicle', ['vehicle_id' => $vehicle->id]);
        }

        // Check if there's any data to index
        if (!empty($bulkData)) {
            try {
                // Debug Log: Check the structure of the bulk data being sent
                Log::info('Bulk Index Data: ', ['bulk_data' => json_encode($bulkData)]);

                // Execute the bulk request
                $response = $client->bulk(['body' => $bulkData]);

                // Debug Log: Response from Elasticsearch
                Log::info('Bulk Indexing Response: ', ['response' => $response]);

                // Check if response is successful
                if (isset($response['errors']) && $response['errors']) {
                    Log::error('Errors while indexing vehicles', ['errors' => $response['items']]);
                } else {
                    Log::info('Vehicles successfully indexed in both vehicle_records and index_vehicles.');
                }
            } catch (\Exception $e) {
                Log::error('Error in Bulk Indexing: ', ['error' => $e->getMessage()]);
            }
        } else {
            Log::info('No vehicles found for indexing.');
        }
    }
}
