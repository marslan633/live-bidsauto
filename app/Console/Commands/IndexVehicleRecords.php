<?php

namespace App\Console\Commands;

use App\Jobs\StoreVehicleToElasticsearch;
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

        // Only fetch records updated in the last 30 minutes
        $minutes = intval(config('app.elastic_store_time'));


        $cronRun = null;

        // $url = config('app.cron_history_api_url') . '/cron-run-histories';

        try{
            // Remote Connection to KVM4.1
            // $cronRunResponse = Http::timeout(120)->retry(3, 1000)->get($url .'?name=process_vehicles_to_elasticsearch');

            // if ($cronRunResponse->successful()) {
            //     $lastCron = $cronRunResponse->json();
            //     if ($lastCron && $lastCron['end_time']) {
            //         $endTime = Carbon::parse($lastCron['end_time']);
            //         $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));

            //         if ($timeDifference > 20) {
            //             $minutes = $timeDifference + 10;
            //         } elseif ($timeDifference === 20) {
            //             $minutes = $timeDifference + 5;
            //         }
            //     }
            // }

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

            // $cronRunResponse = Http::timeout(120)->retry(3, 1000)->post($url, [
            //     'cron_name' => 'process_vehicles_to_elasticsearch',
            //     'start_time' => now(),
            //     'status' => 'running',
            // ]);

            // if ($cronRunResponse->successful()) {
            //     Log::info('STORE VEHICLES TO ELASTICSEARCH CREATED');
            //     // Handle the successful API cronRunResponse
            //     $cronRun = $cronRunResponse->json()['id'] ?? null; // You can process the data as needed
            //     // Optionally, you can update the cron record with the API response or status
            // } else {
            //     Log::info('Error: STORE VEHICLES TO ELASTICSEARCH CREATED');
            // }


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

            // Get the Elasticsearch auto-generated ID
            $cronRun = $response['_id'];

        }catch(\Exception $e){
            Log::info("Error: STORE VEHICLES TO ELASTICSEARCH: ", ['error' => $e->getMessage()]);
            return;
        }

        $minutes = Carbon::now()->subMinutes($minutes);
        $isFullFetch = config('app.is_full_fetch', false);

        // Set the chunk size to 10,000
        $chunkSize = 10000;
        $dispatchChunkSize = 300;
        // Set the starting point for the query
        $start = 0;
        $query = VehicleRecord::query();
        // Check if it’s a full fetch or an incremental fetch
        if ($isFullFetch) {
            // Full fetch: fetch all records without any filter
            $query->whereNotNull('sale_date');
        } else {
            // Fetch records that were updated after $minutes
            $query->where('updated_at', '>=', $minutes)
          ->whereNotNull('sale_date');
        }

        // Loop through the records in chunks of 10,000
        while (true) {
            // Fetch 10,000 records at a time using skip and take
            $vehicles = $query->skip($start)->take($chunkSize)->get();

            // If no records are fetched, exit the loop (we've reached the end)
            if ($vehicles->isEmpty()) {
                break;
            }

            // Dispatch the job for this chunk
            $vehicles->chunk($dispatchChunkSize)->each(function ($chunk) {
            Log::info('Dispatching Store Vehicle To Elastic Search Job');
                dispatch(new StoreVehicleToElasticsearch($chunk));
            });

            // Increase the starting point for the next chunk (i.e., skip the previous 10,000 records)
            $start += $chunkSize;
        }

        $this->info('✅ Indexing vehicle_records completed!');

        // if(config('app.is_full_fetch') == true){
        //     VehicleRecord::chunk(500, function ($vehicles) use ($clientKvmFour) {
        //         foreach ($vehicles as $vehicle) {
        //             $clientKvmFour->index([
        //                 'index' => 'vehicle_records',
        //                 'id' => $vehicle->id,
        //                 'body' => $vehicle->toArray(),
        //             ]);
        //         }
        //     });
        // }else{
        //     VehicleRecord::where('updated_at', '>=', $minutes)
        //     ->chunk(500, function ($vehicles) use ($clientKvmFour) {
        //         foreach ($vehicles as $vehicle) {
        //             $clientKvmFour->index([
        //                 'index' => 'vehicle_records',
        //                 'id' => $vehicle->id,
        //                 'body' => $vehicle->toArray(),
        //             ]);
        //         }
        //     });
        // }

        if($cronRun){

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

                Log::info('STORE VEHICLES TO ELASTICSEARCH CREATED');
            } else {
                Log::info('ERROR: STORE VEHICLES TO ELASTICSEARCH CREATED');


        }



        $this->info('✅ Indexing vehicle_records completed!');
    }

}
