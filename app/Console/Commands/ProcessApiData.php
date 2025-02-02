<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, CacheKey
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;

class ProcessApiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:api-data';

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
        $this->info("Process started at: " . $startDateTime);
        \Log::info("Process started at: " . $startDateTime);

        // Get the last cron job status
        $lastCron = DB::table('cron_run_history')
            ->where('cron_name', 'process_vehicle_data')
            ->latest('start_time')
            ->first();

        $minutes = 20; // Default minutes value
    
        if ($lastCron && $lastCron->status === 'failed') {
            $minutes = $lastCron->minutes + 20; // Double the minutes if last run failed
            $this->info("Last cron job failed. Updating minutes to: {$minutes}");
            \Log::info("Last cron job failed. Updating minutes to: {$minutes}");
        }

        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name' => 'process_vehicle_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'minutes' => $minutes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // $minutes = 20; // Time frame in minutes
        $perPage = 1000; // Records per page
        $baseUrl = 'http://carstat.dev/api/cars';
        $totalPages = 0;

        try {
            // Fetch total records from the API
            $response = Http::withHeaders([
                'x-api-key' => env('CAR_API_KEY'),
            ])
            ->timeout(120)
            ->retry(3, 1000)
            ->get("{$baseUrl}?minutes={$minutes}&per_page={$perPage}");

            if ($response->successful()) {
                $totalRecords = $response->json()['meta']['total'] ?? 0;

                // Update the cron_run_history table with the total records
                DB::table('cron_run_history')
                    ->where('id', $cronRun)
                    ->update([
                        'total_records' => $totalRecords,
                        'updated_at' => now(),
                    ]);
                    
                $this->info("Fetching total number of record: {$totalRecords}");
                \Log::info("Fetching total number of record: {$totalRecords}");

                if ($totalRecords > 0) {
                    $totalPages = ceil($totalRecords / $perPage);
                    $this->info("Total Pages: $totalPages");
                    \Log::info("Total Pages: $totalPages");

                    $allData = []; // Array to accumulate all data

                    for ($page = 1; $page <= $totalPages; $page++) {
                        $apiUrl = "{$baseUrl}?minutes={$minutes}&page={$page}&per_page={$perPage}";

                        $this->info("Fetching page {$page} of {$totalPages}");
                        \Log::info("Fetching page {$page} of {$totalPages}, URL: {$apiUrl}");

                        $pageResponse = Http::withHeaders([ 
                            'x-api-key' => env('CAR_API_KEY'),
                        ])
                        ->timeout(120)
                        ->retry(3, 1000)
                        ->get($apiUrl);

                        if ($pageResponse->successful()) {
                            $data = $pageResponse->json()['data'] ?? [];
                            
                            // Append data to allData array
                            // $allData = array_merge($allData, $data);
                            
                            // Save all data to cache with a unique cache key
                            $cacheKey = 'vehicle_data_' . now()->format('Y_m_d_H_i_s');
                            $expiresAt = now()->addMinutes(240); // Store for 4 hour
                            $this->info("cache key {$cacheKey}.");
                            \Log::info("cache key {$cacheKey}.");
                            
                            if (count($data) > 0) {     
                                Cache::put($cacheKey, $data, $expiresAt); 

                                // Save cache details to database
                                CacheKey::updateOrCreate(
                                    ['cache_key' => $cacheKey],
                                    [
                                        'status' => 'pending',
                                        'expires_at' => $expiresAt,
                                    ]
                                );

                                $this->info("Data saved in cache with key: {$cacheKey}");
                                \Log::info("Data saved in cache with key: {$cacheKey}");
                            } else {
                                \Log::info("No data to cache. Skipping cache storage for key: {$cacheKey}");
                            }

                            $this->info("Page {$page} processed successfully.");
                            \Log::info("Page {$page} processed successfully.");
                        } else {
                            $this->error("Failed to fetch data for page {$page}.");
                            \Log::error("Failed to fetch data for page {$page}.");
                            break;
                        }
                    }

                    
                } else {
                    $this->info("No records to process.");
                    \Log::info("No records to process.");
                }
            } else {
                $this->error("Failed to fetch total records.");
                \Log::error("Failed to fetch total records.");
            }

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);


            // Log the ending time of the process
            $endTime = microtime(true);
            $endDateTime = Carbon::now();
            $this->info("Process ended at: " . $endDateTime);
            \Log::info("Process ended at: " . $endDateTime);

            // Calculate the total execution time
            $executionTime = $endTime - $startTime; // In seconds, including fractions
            $formattedTime = round($executionTime, 2); // Round to 2 decimal places
            $this->info("Total execution time: {$formattedTime} seconds");
            \Log::info("Total execution time: {$formattedTime} seconds");


            $this->info('Data processing completed.');
            \Log::info('Data processing completed.');

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            \Log::error("Error: " . $e->getMessage());

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // Send email notification
            $cronJobName = 'process_vehicle_data';
            $adminEmails = explode(',', env('ADMIN_EMAIL'));
            Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }
    }
}