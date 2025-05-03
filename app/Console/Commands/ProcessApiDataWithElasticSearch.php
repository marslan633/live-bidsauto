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
        $startTime = microtime(true);
        $startDateTime = Carbon::now();

        // if (!Redis::ping()) {
        //     $this->error("❌ Redis is NOT connected!");
        //     return;
        // }

        // **Get Last Successful Cron Job Status**
        $lastCron = CronRunHistory::where('cron_name', 'process_vehicle_data')
            ->where('status', 'success')
            ->latest('start_time')
            ->first();

        $minutes = 400; // Default minutes value

        if ($lastCron && $lastCron->end_time) {
            $endTime = Carbon::parse($lastCron->end_time);
            $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));

            // if (config('app.env') !== 'production') {
            //     $this->info("⏳ Time Difference: {$timeDifference}");
            //     Log::info("⏳ Time Difference: {$timeDifference}");
            // }

            if ($timeDifference > 20) {
                $minutes = $timeDifference + 10;
            } elseif ($timeDifference === 20) {
                $minutes = $timeDifference + 5;
            }
        }

        // if (config('app.env') !== 'production') {
        //     $this->info('🚀 Process started at: '.$startDateTime);
        //     Log::info('🚀 Process started at: '.$startDateTime);
        // }

        // **Store Cron Job Status**
        $cronRun = CronRunHistory::create([
            'cron_name' => 'process_vehicle_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ])->id;

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

                    // Log::info('Stored Cached Data', ['total_records' => count($data)]);

                    // if (config('app.env') !== 'production') {
                    //     $this->info('🎉 Data successfully stored in the database.');
                    // }
                } else {
                    // if (config('app.env') !== 'production') {
                    //     $this->info('⚠️ No new data available.');
                    // }
                }

                // **Get 'next' page URL**
                $nextUrl = $response->json()['links']['next'] ?? null;
                $this->info('Next URL.', $nextUrl);
                $apiUrl = $nextUrl ?: null;

            } while ($nextUrl !== null);

            // **Mark Cron as Success**
            CronRunHistory::where('_id', $cronRun)->update([
                'end_time' => now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);


        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            // \Log::error("❌ Error: " . $e->getMessage());

            CronRunHistory::where('_id', $cronRun)->update([
                'end_time' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

        }
    }
}
