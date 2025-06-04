<?php

namespace App\Console\Commands\KvmFour;

use App\Jobs\KvmFour\StoreVehicleToElasticsearch;
use Illuminate\Console\Command;
use App\Models\VehicleRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IndexVehicleRecords extends Command
{
    protected $signature = 'index:vehicle-records';
    protected $description = 'Index all vehicle records to Elasticsearch';

    public function handle()
    {
        $startDateTime = Carbon::now();
        $this->info("Index Vehicles Process started at: " . $startDateTime);
        $this->info("API URL " . config('app.cron_history_api_url'));
        Log::info("Index Vehicles Process started at: " . $startDateTime);

        $clientKvmOne = app('ElasticsearchKvmOne');
        $clientKvmFour = app('ElasticsearchKvmFour');

        $minutes = intval(config('app.elastic_store_time'));
        $cronRun = null;

        try {
            // Fetch the last successful cron run history
            $response = $clientKvmOne->search([
                'index' => 'cron_run_histories',
                'body' => [
                    'size' => 1,
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['cron_name' => 'process_archived_vehicle_data']],
                                ['term' => ['status' => 'success']]
                            ]
                        ]
                    ],
                    'sort' => [
                        ['start_time' => ['order' => 'desc']]
                    ]
                ]
            ]);
            $hits = $response['hits']['hits'];

            if (!empty($hits)) {
                $lastCron = $hits[0]['_source'];
                if (!empty($lastCron['end_time'])) {
                    $endTime = Carbon::parse($lastCron['end_time']);
                    $timeDifference = max(0, $endTime->diffInMinutes(now()));

                    if ($timeDifference > 20) {
                        $minutes = $timeDifference + 10;
                    } elseif ($timeDifference === 20) {
                        $minutes = $timeDifference + 5;
                    }
                }
            }

            // Record the new cron run history
            $params = [
                'index' => 'cron_run_histories',
                'body' => [
                    'cron_name'   => 'process_vehicles_to_elasticsearch',
                    'start_time'  => $startDateTime->toIso8601String(),
                    'status'      => 'running',
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ],
            ];
            $response = $clientKvmOne->index($params);
            $cronRun = $response['_id'];
        } catch (\Exception $e) {
            $clientKvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.4',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'index:vehicle-records',
                    'error' => 'Error: STORE VEHICLES TO ELASTICSEARCH: ' . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
            return;
        }

        // Fetch records either incrementally or in full
        $minutes = Carbon::now()->subMinutes($minutes);
        $isFullFetch = config('app.is_full_fetch', false);

        $chunkSize = 500;  // Process records in chunks of 500
        $dispatchChunkSize = 100;

        $query = VehicleRecord::query();

        // Full fetch or incremental fetch logic
        if ($isFullFetch) {
            $query->whereNotNull('sale_date');
        } else {
            $query->where('updated_at', '>=', $minutes)
                  ->whereNotNull('sale_date');
        }
        $query->where('data_source', 1);
        // Use Laravel's chunk method to process records in batches of 500
        $query->chunk($chunkSize, function ($vehicles) use ($dispatchChunkSize) {
            // Dispatch the job with the chunk, which will include relationships eager-loaded in the job
            dispatch(new StoreVehicleToElasticsearch($vehicles));
        });

        $this->info('✅ Indexing vehicle_records completed!');

        // Update the cron history status to success
        if ($cronRun) {
            $clientKvmOne->update([
                'index' => 'cron_run_histories',
                'id'    => $cronRun,
                'body'  => [
                    'doc' => [
                        'end_time'   => now()->toIso8601String(),
                        'status'     => 'success',
                        'updated_at' => now()->toIso8601String(),
                    ]
                ]
            ]);
            Log::info('STORE VEHICLES TO ELASTICSEARCH SUCCESS');
        } else {
            $clientKvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.4',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'index:vehicle-records',
                    'error' => 'ERROR: STORE VEHICLES TO ELASTICSEARCH FAILED',
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }
}
