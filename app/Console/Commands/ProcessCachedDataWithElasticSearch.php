<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use Illuminate\Support\Facades\Log;

class ProcessCachedDataWithElasticSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-data-with-elasticsearch';

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
        // $this->info("Process started at: " . $startDateTime);
        // Log::info("Process started at: " . $startDateTime);
        $client = app('ElasticsearchKvmOne');
        $cronRun = null;

        try {

            // Always Will Run On Default Server
            $params = [
                'index' => 'cron_run_histories',
                'body'  => [
                    'cron_name'   => 'process_cached_data',
                    'start_time'  => $startDateTime->toIso8601String(),
                    'status'      => 'running',
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ]
            ];

            $response = $client->index($params);
            $cronRun = $response['_id'];

             // Fetch 200 recent documents from Elasticsearch
             $response = $client->search([
                'index' => 'vehicle_api_data',
                'size' => 200,
                'sort' => ['created_at:desc'],
                '_source' => ['cache_value'], // optional optimization
            ]);

            $hits = $response['hits']['hits'];

            if (empty($hits)) {
                $client->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.1',
                        'error_type' => 'General Error',
                        'command_name' => 'process:cached-data-with-elasticsearch',
                        'error' => "No cached data found.",
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
                return;
            }

        } catch (\Exception $e) {
            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.1',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process:cached-data-with-elasticsearch',
                    'error' => json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
            $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            return;
        }

        foreach ($hits as $doc) {
            try {
                $this->info("Processing document...");
                $docId = $doc['_id'];
                $compressed = $doc['_source']['cache_value'];

                $data = unCompressData($compressed);

                if (!$data) {
                    $this->info('Decompressed data is empty or invalid.');
                    continue;
                }

                $processDataForCache = [];

                foreach ($data as $car) {
                    $processedCar = convertAndStoreDataToRedis($car);
                    $processDataForCache[] = $processedCar;
                }

                Log::info('Processed Count', ['count' => count($processDataForCache)]);

                // Store in new index
                $client->index([
                    'index' => 'vehicle_process_cached_api_data',
                    'body' => [
                        'cache_value' => compressData($processDataForCache),
                        'status' => 'pending',
                        'created_at' => now()->format('Y-m-d H:i:s'),
                        'updated_at' => now()->format('Y-m-d H:i:s'),
                        'expires_at' => Carbon::now()->addDays(7)->format('Y-m-d H:i:s'),
                    ]
                ]);

                $response = $client->exists([
                    'index' => 'vehicle_api_data',
                    'id'    => $docId,
                ]);

                // If the document exists, delete it
                if ($response) {
                    $client->delete([
                        'index' => 'vehicle_api_data',
                        'id'    => $docId,
                    ]);
                    Log::info("Document with ID $docId deleted successfully.");
                } else {
                    Log::info("Document with ID $docId not found, skipping delete.");
                }


            } catch (\Exception $e) {
                $client->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.1',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'process:cached-data-with-elasticsearch',
                        'error' => "Error processing doc ID {$doc['_id']}: " . json_encode($e->getMessage()),
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
                continue;
            }
        }


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

    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        // Always Run on Defautl Server
        $client = app('ElasticsearchKvmOne');

        $client->update([
            'index' => 'cron_run_histories',
            'id'    => $cronRun, // existing document ID
            'body'  => [
                'doc' => [
                    'end_time'       => now()->toIso8601String(),
                    'status'         => 'failed',
                    'error_message'  => $errorMessage,
                    'updated_at'     => now()->toIso8601String(),
                ]
            ]
        ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }

}
