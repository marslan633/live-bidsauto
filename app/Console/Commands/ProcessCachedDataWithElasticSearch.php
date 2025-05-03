<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleApiData;
use App\Models\VehicleProcessCachedApiData;
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
        $client = app('Elasticsearch');
        try {

            // Always Will Run On Default Server
            $cronRun = CronRunHistory::create([
                'cron_name' => 'process_cached_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ])->_id;

             // Fetch 200 recent documents from Elasticsearch
             $response = $client->search([
                'index' => 'vehicle_api_data',
                'size' => 200,
                'sort' => ['created_at:desc'],
                '_source' => ['cache_value'], // optional optimization
            ]);

            $hits = $response['hits']['hits'];

            if (empty($hits)) {
                $this->info("No cached data found.");
                return;
            }

        } catch (\Exception $e) {
            Log::info("Error fetching cache keys: ", ['data' => json_encode($e->getMessage())]);
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
                        'created_at' => now()->format('Y-m-d H:i:s'),
                        'updated_at' => now()->format('Y-m-d H:i:s'),
                        'expires_at' => Carbon::now()->addDays(7)->format('Y-m-d H:i:s'),
                    ]
                ]);

                // Delete old document
                $client->delete([
                    'index' => 'vehicle_api_data',
                    'id' => $docId,
                ]);

            } catch (\Exception $e) {
                Log::info("Error processing doc ID {$doc['_id']}: " . $e->getMessage());
                continue;
            }
        }


        CronRunHistory::where('_id', $cronRun)->update([
            'end_time' => now(),
            'status' => 'success',
            'updated_at' => now(),
        ]);

    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        Log::error($errorMessage);
        // Always Run on Defautl Server
        CronRunHistory::where('_id', $cronRun)->update([
            'end_time' => now(),
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }

}
