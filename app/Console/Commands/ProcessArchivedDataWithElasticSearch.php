<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleArchivedApiData;
use Illuminate\Support\Facades\Log;

class ProcessArchivedDataWithElasticSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:archived-data-with-elasticsearch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and process data from third-party API and save it into the database';

    /**
     * Execute the console command.
     */

    /**
     * Below Function Implementation store data into cache.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $client = app('ElasticsearchKvmOne');

        $response = $client->search([
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

        $minutes = 4320; // Default minutes value

        if (!empty($hits)) {
            $lastCron = $hits[0]['_source'];
            if (!empty($lastCron['end_time'])) {
                $endTime = Carbon::parse($lastCron['end_time']);
                $timeDifference = max(0, $endTime->diffInMinutes(now()));

                // if ($timeDifference > 20) {
                //     $minutes = $timeDifference + 10;
                // } elseif ($timeDifference === 20) {
                //     $minutes = $timeDifference + 5;
                // }
            }
        }

        $params = [
            'index' => 'cron_run_histories',
            'body' => [
                'cron_name'   => 'process_archived_vehicle_data',
                'start_time'  => $startDateTime->toIso8601String(),
                'status'      => 'running',
                'created_at'  => now()->toIso8601String(),
                'updated_at'  => now()->toIso8601String(),
            ],
        ];

        $response = $client->index($params);

        // Get the Elasticsearch auto-generated ID
        $cronRun = $response['_id'];

        $perPage = 1000;
        $baseUrl = 'http://carstat.dev/api/archived-lots';

        $apiUrl = "{$baseUrl}?per_page={$perPage}&minutes={$minutes}&simple_paginate=1&page=1";

        // if(config('app.env') !== 'production'){
        // Log::info("API: {$apiUrl}");
        // }

        try {
            do {
                // Fetch fresh data from API
                $response = Http::withHeaders([
                    'x-api-key' => config('app.car_api_key'),
                ])
                ->timeout(120)
                ->retry(3, 1000)
                ->get($apiUrl);
                if(config('app.env') !== 'production'){
                    Log::info("API URL: {$apiUrl}");
                }
                if ($response->successful()) {
                    $data = $response->json()['data'] ?? [];

                    if (!empty($data)) {
                        // Convert the data array into a collection
                        $dataCollection = collect($data);

                        // Chunk the collection into smaller collections of 200 items each
                        $dataCollection->chunk(200)->each(function ($chunk) {
                            $this->info('Data Being Inserted For Archvied');
                            // Prepare the chunk for insertion
                            $client = app('ElasticsearchKvmOne');

                             // Prepare the chunk as one document
                            $insertData = [
                                'cache_value' => compressData($chunk->toArray()),
                                'created_at'  => now()->toIso8601String(),
                                'updated_at'  => now()->toIso8601String(),
                                'status' => 'pending',
                                'expires_at'  => Carbon::now()->addDays(7)->toIso8601String()
                            ];

                            // Insert the chunk into Elasticsearch
                            $client->index([
                                'index' => 'vehicle_archived_api_data',
                                'body'  => $insertData
                            ]);

                        });

                        // Log::info('Stored Cached Data', ['total_records' => count($data)]);

                        // if (config('app.env') !== 'production') {
                        //     $this->info('🎉 Data successfully stored in the database.');
                        // }
                    } else {
                        // if (config('app.env') !== 'production') {
                        //     $this->info('⚠️ No new data available.');
                        // }
                    }
                } else {
                    // if(config('app.env') !== 'production'){
                    //     $this->error('Failed to fetch API data.');
                    //     Log::info('Failed to fetch API data.');
                    // }
                    break;
                }

                // if(config('app.env') !== 'production'){
                //     $this->info('Data processed successfully.');
                //     Log::info('Data processed successfully.');
                // }
                // Get 'next' page URL
                $nextUrl = $response->json()['links']['next'] ?? null;
                if ($nextUrl) {
                    // Check if 'per_page' and 'simple_paginate' exist in the next URL
                    $queryParams = [];

                    if (!str_contains($nextUrl, 'per_page=')) {
                        $queryParams[] = "per_page={$perPage}";
                    }

                    if (!str_contains($nextUrl, 'simple_paginate=')) {
                        $queryParams[] = "simple_paginate=1";
                    }

                    if (!str_contains($nextUrl, 'minutes=')) {
                        $queryParams[] = "minutes={$minutes}";
                    }

                    if (!empty($queryParams)) {
                        $separator = str_contains($nextUrl, '?') ? '&' : '?';
                        $nextUrl .= $separator . implode('&', $queryParams);
                    }

                    $apiUrl = $nextUrl;
                } else {
                    // Update cron_run_history with success status
                    $client->update([
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
                }
            } while ($nextUrl !== null);
        } catch (\Exception $e) {
            if(config('app.env') !== 'production'){
                $this->error("Error: " . $e->getMessage());
                Log::error("Error: " . $e->getMessage());
            }

            $client->update([
                'index' => 'cron_run_histories',
                'id'    => $cronRun,
                'body'  => [
                    'doc' => [
                        'end_time'       => now()->toIso8601String(),
                        'status'         => 'failed',
                        'error_message'  => $e->getMessage(),
                        'updated_at'     => now()->toIso8601String(),
                    ]
                ]
            ]);


            // Send email notification
            $cronJobName = 'process_archived_vehicle_data';
            $adminEmails = explode(',', env('ADMIN_EMAIL'));
            Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }

    }
}
