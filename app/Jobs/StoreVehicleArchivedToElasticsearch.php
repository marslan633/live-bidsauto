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
     * The name of the job.
     *
     * @var string
     */
    public $jobName = 'store_archived_vehicle_records_to_elasticsearch_job';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($vehicles)
    {
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
            $bulkData[] = ['index' => ['_index' => 'vehicle_record_archiveds', '_id' => $vehicle->id]];
            $bulkData[] = $vehicle->toArray();
        }

        if (!empty($bulkData)) {
            $client->bulk(['body' => $bulkData]);
            Log::info('Archived vehicle records indexed in Elasticsearch by job.');
        }
    }
}
