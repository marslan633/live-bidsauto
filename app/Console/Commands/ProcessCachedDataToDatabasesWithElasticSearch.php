<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataToDatabaseJob;
use App\Jobs\ProcessCachedDataToDatabaseJobWithElasticSearch;
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

        $client = app('Elasticsearch');

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

            $response = $client->index($params);

            if ($response) {
                Log::info('PROCESS CACHED DATA TO DATABASE CREATED');
                // Handle the successful API cronRunResponse
                $cronRun = $response['_id'];
                // Optionally, you can update the cron record with the API response or status
            } else {
                Log::info('Error: PROCESS CACHED DATA TO DATABASE CREATED');
            }

            // Fetch data from Elasticsearch index
            $response = $client->search([
                'index' => 'vehicle_process_cached_api_data',
                'size' => 100,
                'sort' => ['created_at:desc']
            ]);

            $hits = $response['hits']['hits'];

            if (count($hits) === 0) {
                $this->info("No Data Pending to process");
                Log::info('NO DATA: vehicle_process_cached_api_data index empty');
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
        }else{

            Log::info('ERROR: PROCESS CACHED DATA TO DATABASE UPDATED');
        }


    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        Log::error($errorMessage);
            $client = app('Elasticsearch');

            $client->update([
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
