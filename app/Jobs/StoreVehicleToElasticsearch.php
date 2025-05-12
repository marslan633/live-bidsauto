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

            // Prepare bulk data for Elasticsearch
            $bulkData[] = ['index' => ['_index' => 'vehicle_records', '_id' => $vehicle->id]];
            $bulkData[] = $vehicle->toArray();  // Index the vehicle and all of its relations

            // Optionally, log the vehicle data being indexed (for debugging)
            Log::info('Indexing vehicle to Elasticsearch', ['vehicle_id' => $vehicle->id]);
        }

        // Perform bulk indexing if there is data
        if (!empty($bulkData)) {
            $client->bulk(['body' => $bulkData]);
            Log::info('Vehicle records indexed in Elasticsearch.');
        }
    }
}
