<?php

namespace App\Console\Commands;

use App\Models\CacheKey;
use App\Models\CronRunHistory;
use App\Models\VehicleApiData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessApiDataWithElasticSearch extends Command
{
    protected $signature = 'process:api-data-with-elasticsearch';

    protected $description = 'Fetch data from API and push it to Redis';

    public function handle()
    {
        $startDateTime = Carbon::now();

        // **Get Last Successful Cron Job Status**
        $client = app('Elasticsearch');

        $response = $client->search([
            'index' => 'cron_run_histories',
            'body' => [
                'size' => 1,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['cron_name' => 'process_vehicle_data']],
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

        $minutes = 400;

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


        $params = [
            'index' => 'cron_run_histories',
            'body' => [
                'cron_name'   => 'process_vehicle_data',
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
        $baseUrl = 'http://carstat.dev/api/cars';

        if (config('app.is_full_fetch') === true) {
            $apiUrl = "{$baseUrl}?per_page={$perPage}&simple_paginate=1&page=1";
        } else {
            $apiUrl = "{$baseUrl}?per_page={$perPage}&minutes={$minutes}&simple_paginate=1&page=1";
        }

        try {
            do {
                // **Fetch Fresh Data from API**
                $response = Http::withHeaders([
                    'x-api-key' => config('app.car_api_key'),
                ])
                    ->timeout(120)
                    ->retry(3, 1000)
                    ->get($apiUrl);

                if (! $response->successful()) {
                    $this->error('❌ Failed to fetch API data.');
                    // \Log::error('❌ Failed to fetch API data.');
                    break;
                }

                $data = $response->json()['data'] ?? null;

                if (!empty($data)) {
                    // Convert the data array into a collection
                    $dataCollection = collect($data);

                    // Chunk the collection into smaller collections of 200 items each
                    $dataCollection->chunk(200)->each(function ($chunk) {
                        $client = app('Elasticsearch');
                        $params = ['body' => []];
                        $now = now();
                        $expiry = Carbon::now()->addDays(7);

                        $params['body'][] = [
                            'index' => [
                                '_index' => 'vehicle_api_data',
                            ]
                        ];

                        $params['body'][] = [
                            'cache_value' => compressData($chunk->toArray()),
                            'created_at' => $now->format('Y-m-d H:i:s'),
                            'expires_at' => $expiry->format('Y-m-d H:i:s'),
                        ];

                        $client->bulk($params);
                    });

                }
                // **Get 'next' page URL**
                $nextUrl = $response->json()['links']['next'] ?? null;
                $this->info('Next URL.', $nextUrl);
                $apiUrl = $nextUrl ?: null;

            } while ($nextUrl !== null);

            // **Mark Cron as Success**
            $client->update([
                'index' => 'cron_run_histories',
                'id'    => $cronRun, // This is the _id returned earlier
                'body'  => [
                    'doc' => [
                        'end_time'    => now()->toIso8601String(),
                        'status'      => 'success',
                        'updated_at'  => now()->toIso8601String(),
                    ]
                ]
            ]);


        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            // \Log::error("❌ Error: " . $e->getMessage());

            $client->update([
                'index' => 'cron_run_histories',
                'id'    => $cronRun, // This is the Elasticsearch document _id
                'body'  => [
                    'doc' => [
                        'end_time'       => now()->toIso8601String(),
                        'status'         => 'failed',
                        'error_message'  => $e->getMessage(),
                        'updated_at'     => now()->toIso8601String(),
                    ]
                ]
            ]);

        }
    }
}
