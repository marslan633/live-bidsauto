<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use Illuminate\Support\Facades\Log;

class ProcessArchivedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:archived-data';

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
        $startTime = microtime(true);
        $startDateTime = Carbon::now();

        if(config('app.env') !== 'production'){
            $this->info("Process started at: " . $startDateTime);
            Log::info("Process started at: " . $startDateTime);
        }

        // Get the last cron job status
        $lastCron = DB::connection('mysql')->table('cron_run_history')
            ->where('cron_name', 'process_archived_vehicle_data')
            ->where('status', 'success')
            ->latest('start_time')
            ->first();

        $minutes = 2500; // Default minutes value

        if ($lastCron && $lastCron->end_time) {
            // Convert end_time to Carbon instance
            $endTime = Carbon::parse($lastCron->end_time);

            // Get the difference in minutes (ensure it's a non-negative integer)
            $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));
        if(config('app.env') !== 'production'){
            $this->info("Time Difference: {$timeDifference}");
            Log::info("Time Difference: {$timeDifference}");
        }

            // Apply the new conditions
            if ($timeDifference > 20) {
                $minutes += $timeDifference + 10;
            } elseif ($timeDifference === 20) {
                $minutes += $timeDifference + 5;
            }
        }

        if(config('app.env') !== 'production'){
        $this->info("Minutes Parameter After Checking: {$minutes}");
        Log::info("Minutes Parameter After Checking: {$minutes}");
        }
        $cronRun = DB::connection('mysql')->table('cron_run_history')->insertGetId([
            'cron_name' => 'process_archived_vehicle_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'minutes' => $minutes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $perPage = 1000;
        $baseUrl = 'http://carstat.dev/api/archived-lots';

        $apiUrl = "{$baseUrl}?per_page={$perPage}&minutes={$minutes}&simple_paginate=1&page=1";

        if(config('app.env') !== 'production'){
        Log::info("API: {$apiUrl}");
        }

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
                    $cacheKey = 'vehicle_archived_data_' . now()->format('Y_m_d_H_i_s');
                    $expiresAt = now()->addMinutes(intval(config('app.cache_key_expiry'))); // Store for 8 hours

                    if (count($data) > 0) {

                        // Save cache details to database
                        DB::connection('mysql_remote')->table('cache_keys')->updateOrInsert(
                            ['cache_key' => $cacheKey],
                            [
                                'status' => 'pending',
                                'cache_value' => json_encode($data),
                                'expires_at' => $expiresAt,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]
                        );

                        if(config('app.env') !== 'production'){
                            $this->info("Data saved in cache with key: {$cacheKey}");
                            Log::info("Data saved in cache with key: {$cacheKey}");
                        }
                    } else {
                        if(config('app.env') !== 'production'){
                            Log::info("No data to cache. Skipping cache storage for key: {$cacheKey}");
                        }
                    }
                } else {
                    if(config('app.env') !== 'production'){
                        $this->error('Failed to fetch API data.');
                        Log::info('Failed to fetch API data.');
                    }
                    break;
                }

                if(config('app.env') !== 'production'){
                    $this->info('Data processed successfully.');
                    Log::info('Data processed successfully.');
                }
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
                    DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
                        'end_time' => Carbon::now(),
                        'status' => 'success',
                        'updated_at' => now(),
                    ]);

                    if(config('app.env') !== 'production'){
                        $this->info('No more pages to fetch.');
                        Log::info('No more pages to fetch.');
                    }
                }
            } while ($nextUrl !== null);
        } catch (\Exception $e) {
            if(config('app.env') !== 'production'){
                $this->error("Error: " . $e->getMessage());
                Log::error("Error: " . $e->getMessage());
            }
            DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // Send email notification
            $cronJobName = 'process_archived_vehicle_data';
            $adminEmails = explode(',', env('ADMIN_EMAIL'));
            Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }

    }
}
