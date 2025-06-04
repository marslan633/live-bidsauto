<?php

namespace App\Console\Commands\KvmThree;

use App\Jobs\ProcessCachedDataToDatabaseJob;
use App\Jobs\KvmThree\ProcessCachedDataToDatabaseJobWithElasticSearch;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Http, Mail, Log};
use App\Mail\CronJobFailedMail;

class ProcessCachedDataToDatabasesWithElasticSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:process-cached-data-to-databases-with-elasticsearch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process cached data into database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $this->info("Process started at: " . $startDateTime);
        $this->info("API URL " . config('app.cron_history_api_url'));
        Log::info("Process started at: " . $startDateTime);

        $cronRun = null;

        $clientkvmOne = app('ElasticsearchKvmOne');


        try{

            $params = [
                'index' => 'cron_run_histories',
                'body'  => [
                    'cron_name'   => 'process_cached_data_to_database',
                    'start_time'  => $startDateTime->toIso8601String(),
                    'status'      => 'running',
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ]
            ];

            $response = $clientkvmOne->index($params);

            if ($response['_id']) {
                Log::info('PROCESS CACHED DATA TO DATABASE CREATED');
                // Handle the successful API cronRunResponse
                $cronRun = $response['_id'];
                // Optionally, you can update the cron record with the API response or status
            } else {
                $clientkvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'process:process-cached-data-to-databases-with-elasticsearch',
                        'error' => 'Error: PROCESS CACHED DATA TO DATABASE CREATED',
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }

            // Fetch data from Elasticsearch index
            $response = $clientkvmOne->search([
                'index' => 'vehicle_process_cached_api_data',
                'size' => 100, // Number of records to return
                'sort' => [
                    'created_at:desc' // Sort by created_at in descending order
                ],
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'match' => [
                                        'status' => 'pending' // Match only records with status 'pending'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);


            $hits = $response['hits']['hits'];

            if (count($hits) === 0) {
                // $clientkvmOne->index([
                //     'index' => 'error_logs',
                //     'body' => [
                //         'server_name' => 'KVM4.3',
                //         'error_type' => 'General',
                //         'command_name' => 'process:process-cached-data-to-databases-with-elasticsearch',
                //         'error' => 'NO DATA: vehicle_process_cached_api_data index empty',
                //         'created_at' => now()->toIso8601String(),
                //         'updated_at' => now()->toIso8601String(),
                //     ],
                // ]);
                return;
            }

            // Extract source
            $data = array_map(function ($item) {
                $source = $item['_source'];
                $source['_id'] = $item['_id']; // Keep doc ID if needed
                return $source;
            }, $hits);

        }catch(\Exception $e){
            // if($cronRun !== null){
                $clientkvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'process:process-cached-data-to-databases-with-elasticsearch',
                        'error' => 'Error fetching cache keys: ' . json_encode($e->getMessage()),
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);

                $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            // }
            return;
        }

        // **Batch processing setup**
        // Initialize an empty array to hold the jobs
        collect($data)->chunk(100)->each(function ($chunk) {
            foreach ($chunk as $item) {
                // Log::info('Data For Database', ['item' => json_encode($item)]);
                ProcessCachedDataToDatabaseJobWithElasticSearch::dispatch((object)$item);
            }
        });
        if($cronRun){
            $clientkvmOne->update([
                'index' => 'cron_run_histories',
                'id'    => $cronRun, // previously captured _id
                'body'  => [
                    'doc' => [
                        'end_time'    => now()->toIso8601String(),
                        'status'      => 'success',
                        'updated_at'  => now()->toIso8601String(),
                    ]
                ]
            ]);

            Log::info('PROCESS CACHED DATA TO DATABASE UPDATED');
        }else{
            $clientkvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process:process-cached-data-to-databases-with-elasticsearch',
                    'error' => 'ERROR: PROCESS CACHED DATA TO DATABASE UPDATED',
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }


    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
            $clientkvmOne = app('ElasticsearchKvmOne');

            $clientkvmOne->update([
                'index' => 'cron_run_histories',
                'id'    => $cronRun, // previously captured _id
                'body'  => [
                    'doc' => [
                        'end_time'    => now()->toIso8601String(),
                        'status'      => 'failed',
                        'updated_at'  => now()->toIso8601String(),
                    ]
                ]
            ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }


}
