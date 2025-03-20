<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleArchivedApiData;
use Illuminate\Support\Facades\Log;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;

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

        // if(config('app.env') !== 'production'){
        //     $this->info("Process started at: " . $startDateTime);
        //     Log::info("Process started at: " . $startDateTime);
        // }

        // Get the last cron job status
        $lastCron = CronRunHistory::where('cron_name', 'process_archived_vehicle_data')
        ->where('status', 'success')
        ->orderBy('start_time', 'desc')
        ->first();


        $minutes = 2500; // Default minutes value

        if ($lastCron && $lastCron->end_time) {
            // Convert end_time to Carbon instance
            $endTime = Carbon::parse($lastCron->end_time);

            // Get the difference in minutes (ensure it's a non-negative integer)
            $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));
        // if(config('app.env') !== 'production'){
        //     $this->info("Time Difference: {$timeDifference}");
        //     Log::info("Time Difference: {$timeDifference}");
        // }

            // Apply the new conditions
            if ($timeDifference > 20) {
                $minutes += $timeDifference + 10;
            } elseif ($timeDifference === 20) {
                $minutes += $timeDifference + 5;
            }
        }

        // if(config('app.env') !== 'production'){
        // $this->info("Minutes Parameter After Checking: {$minutes}");
        // Log::info("Minutes Parameter After Checking: {$minutes}");
        // }
        $cronRun = CronRunHistory::create([
            'cron_name' => 'process_archived_vehicle_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'minutes' => $minutes,
            'created_at' => now(),
            'updated_at' => now(),
        ])->_id;


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
                            $insertData = [
                                'cache_value' => compressData($chunk->toArray()),
                                'created_at' => now(),
                                'updated_at' => now(),
                                'expires_at' => Carbon::now()->addDays(7)
                            ];

                            // Insert the chunk into the database
                            VehicleArchivedApiData::insert($insertData);
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
                    CronRunHistory::where('_id', $cronRun)->update([
                        'end_time' => now(),
                        'status' => 'success',
                        'updated_at' => now(),
                    ]);


                    // if(config('app.env') !== 'production'){
                    //     $this->info('No more pages to fetch.');
                    //     Log::info('No more pages to fetch.');
                    // }
                }
            } while ($nextUrl !== null);
        } catch (\Exception $e) {
            if(config('app.env') !== 'production'){
                $this->error("Error: " . $e->getMessage());
                Log::error("Error: " . $e->getMessage());
            }
            CronRunHistory::where('_id', $cronRun)->update([
                'end_time' => now(),
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
