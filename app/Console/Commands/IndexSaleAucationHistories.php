<?php

namespace App\Console\Commands;

use App\Jobs\StoreSaleAuctionHistoryToElasticsearch;
use App\Models\SaleAuctionHistory;
use Illuminate\Console\Command;
use App\Models\VehicleRecordArchived;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IndexSaleAucationHistories extends Command
{
    protected $signature = 'index:sale-auction-histories';
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

        $url = config('app.cron_history_api_url') . '/cron-run-histories';


        try{

            // Remote Connection to KVM4.1
            // $cronRunResponse = Http::timeout(120)->retry(3, 1000)->get($url .'?name=process_sale_auction_histories');

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
                                ['term' => ['cron_name' => 'process_sale_auction_histories']],
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
            //     'cron_name' => 'process_sale_auction_histories',
            //     'start_time' => now(),
            //     'status' => 'running',
            // ]);

            // if ($cronRunResponse->successful()) {
            //     Log::info('STORE VEHICLE ARCHIVEDS TO ELASTICSEARCH CREATED');
            //     // Handle the successful API cronRunResponse
            //     $cronRun = $cronRunResponse->json()['id'] ?? null; // You can process the data as needed
            //     // Optionally, you can update the cron record with the API response or status
            // } else {
            //     Log::info('Error: STORE VEHICLE ARCHIVEDS TO ELASTICSEARCH CREATED');
            // }

            $params = [
                'index' => 'cron_run_histories',
                'body' => [
                    'cron_name'   => 'process_sale_auction_histories',
                    'start_time'  => $startDateTime->toIso8601String(),
                    'status'      => 'running',
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ],
            ];

            $response = $clientKvmOne->index($params);

               if ($response['_id']) {
                Log::info('STORE VEHICLE ARCHIVEDS TO ELASTICSEARCH CREATED');
                $cronRun = $response['_id'];
            } else {
                $clientKvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.4',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'index:sale-auction-histories',
                        'error' => 'Error: STORE VEHICLE ARCHIVEDS TO ELASTICSEARCH CREATED',
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }

            // Get the Elasticsearch auto-generated ID

        }catch(\Exception $e){
            $clientKvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.4',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'index:sale-auction-histories',
                    'error' => 'Error: STORE VEHICLE ARCHIVEDS TO ELASTICSEARCH: ' . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
            return;
        }
        $minutes = Carbon::now()->subMinutes($minutes);

        $isFullFetch = config('app.is_full_fetch', false);


             // Set the chunk size to 10,000
        $chunkSize = 1000;  // Process records in chunks of 500
        $dispatchChunkSize = 300;

        $query = $isFullFetch ? SaleAuctionHistory::query() : SaleAuctionHistory::where('updated_at', '>=', $minutes);


        // Use Laravel's chunk method to process records in batches of 500
        $query->chunk($chunkSize, function ($vehicles) use ($dispatchChunkSize) {
            // Dispatch the job with the chunk, which will include relationships eager-loaded in the job
            dispatch(new StoreSaleAuctionHistoryToElasticsearch($vehicles));
        });


        $this->info('✅ Indexing sale_auction_histories job dispatched!');

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

                Log::info('STORE SALE AUCATION HISTORY TO ELASTICSEARCH CREATED');
            } else {
                $clientKvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.4',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'index:sale-auction-histories',
                        'error' => 'ERROR: STORE SALE AUCATION HISTORY TO ELASTICSEARCH CREATED',
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
        }

        $this->info('✅ Indexing vehicle_records completed!');
    }

}
