<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\CacheKey;
use App\Mail\CronJobFailedMail;

class BuyNowDataProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:process-buy-now';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Buy Now data from third-party API and store in cache';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $startTime = microtime(true);
        $startDateTime = Carbon::now();
        $this->info("Process started at: " . $startDateTime);
        \Log::info("Process started at: " . $startDateTime);

        // Get the last cron job status
        $lastCron = DB::table('cron_run_history')
            ->where('cron_name', 'process_buy_now_data')
            ->where('status', 'success')
            ->latest('start_time')
            ->first();

        $minutes = 20;

        if ($lastCron && $lastCron->end_time) {
            // Convert end_time to Carbon instance
            $endTime = Carbon::parse($lastCron->end_time);
            
            // Get the difference in minutes (ensure it's a non-negative integer)
            $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));
            $this->info("Time Difference: {$timeDifference}");
            \Log::info("Time Difference: {$timeDifference}");
            
            // Apply the new conditions
            if ($timeDifference > 20) {
                $minutes = $timeDifference + 10;
            } elseif ($timeDifference === 20) {
                $minutes = $timeDifference + 5;
            }
        }

        $this->info("Minutes Parameter After Checking: {$minutes}");
        \Log::info("Minutes Parameter After Checking: {$minutes}");
        
        // Create an entry in cron_run_history
        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name' => 'process_buy_now_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        
        $perPage = 5000;
        $baseUrl = 'https://carstat.dev/api/fast-prices';
        $apiUrl = "{$baseUrl}?minutes={$minutes}&per_page={$perPage}&page=1";

        try {
            do {
                    // Fetch fresh data from API
                    $response = Http::withHeaders([
                        'x-api-key' => env('CAR_API_KEY'),
                    ])
                    ->timeout(120)
                    ->retry(3, 1000)
                    ->get($apiUrl);

                    if ($response->successful()) {
                        $data = $response->json()['data'] ?? [];
                        $cacheKey = 'buy_now_data_' . now()->format('Y_m_d_H_i_s');
                        $expiresAt = now()->addMinutes(300);

                        if (count($data) > 0) {
                            Cache::put($cacheKey, $data, $expiresAt);
                            CacheKey::updateOrCreate(['cache_key' => $cacheKey], ['status' => 'pending', 'expires_at' => $expiresAt]);
                            $this->info("Data saved in cache with key: {$cacheKey}");
                            \Log::info("Data saved in cache with key: {$cacheKey}");
                        } else {
                            \Log::info("No data to cache. Skipping cache storage for key: {$cacheKey}");
                        }
                    } else {
                        $this->error('Failed to fetch API data.');
                        \Log::info('Failed to fetch API data.');
                        break;
                    }


                $this->info('Data processed successfully.');
                \Log::info('Data processed successfully.');

                // Get 'next' page URL
                $nextUrl = $response->json()['links']['next'] ?? null;
                if ($nextUrl) {
                    $apiUrl = $nextUrl;
                } else {
                    // Update cron_run_history with success status
                    DB::table('cron_run_history')->where('id', $cronRun)->update([
                        'end_time' => Carbon::now(),
                        'status' => 'success',
                        'updated_at' => now(),
                    ]);

                    $this->info('No more pages to fetch.');
                    \Log::info('No more pages to fetch.');
                }

            } while ($nextUrl !== null);

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            \Log::error("Error: " . $e->getMessage());

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            $cronJobName = 'process_buy_now_data';
            $adminEmails = explode(',', env('ADMIN_EMAIL'));
            Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        $this->info("Total execution time: {$executionTime} seconds");
        \Log::info("Total execution time: {$executionTime} seconds");

        $this->info('Data processing completed.');
        \Log::info('Data processing completed.');
    }
}