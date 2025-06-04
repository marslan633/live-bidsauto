<?php

namespace App\Console\Commands\KvmThree;

use App\Jobs\ProcessCachedArchivedDataJob;
use App\Jobs\KvmThree\ProcessCachedArchivedDataJobWithElasticSearch;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleArchivedApiData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessCachedArchivedDataToDatabaseWithElasticSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-archived-data-to-database-with-elasticsearch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch data from cache and save it into the database';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $cronRun = null;
        $client = app('ElasticsearchKvmOne');

        try {

            $params = [
                'index' => 'cron_run_histories',
                'body'  => [
                    'cron_name'   => 'process_cached_archived_data',
                    'start_time'  => $startDateTime->toIso8601String(),
                    'status'      => 'running',
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ]
            ];
            $response = $client->index($params);

            if ($response['_id']) {
                Log::info('PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED');
                // Handle the successful API cronRunResponse
                $cronRun = $response['_id'];
                // Optionally, you can update the cron record with the API response or status
            } else {
                $client->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'process:cached-archived-data-to-database-with-elasticsearch',
                        'error' => 'Error: PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED',
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }

            $response = $client->search([
                'index' => 'vehicle_archived_api_data',
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


            if (count($hits) == 0) {
                // $client->index([
                //     'index' => 'error_logs',
                //     'body' => [
                //         'server_name' => 'KVM4.3',
                //         'error_type' => 'General',
                //         'command_name' => 'process:cached-archived-data-to-database-with-elasticsearch',
                //         'error' => 'NOT DATA:PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED',
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

        } catch (\Exception $e) {
            $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process:cached-archived-data-to-database-with-elasticsearch',
                    'error' => 'Error fetching cache keys: ' . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
            return;
        }

        collect($data)->chunk(100)->each(function ($chunk) {
            foreach ($chunk as $item) {
                // Dispatch a job for each item in the chunk
                ProcessCachedArchivedDataJobWithElasticSearch::dispatch((object)$item);
            }
        });

        if($cronRun){

            $client->update([
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
        } else {
            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process:cached-archived-data-to-database-with-elasticsearch',
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

        $client = app('ElasticsearchKvmOne');

        $client->update([
            'index' => 'cron_run_histories',
            'id'    => $cronRun, // previously captured _id
            'body'  => [
                'doc' => [
                    'end_time'    => now()->toIso8601String(),
                    'status'      => 'failed',
                    'error_message' => $errorMessage,
                    'updated_at'  => now()->toIso8601String(),
                ]
            ]
        ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_archived_data'));
    }



}
