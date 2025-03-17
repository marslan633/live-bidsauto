<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleArchivedApiData;
use App\Models\VehicleProcessCachedArchivedApiData;
use Illuminate\Support\Facades\Log;

class ProcessCachedArchivedDataWithoutQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-archived-data-wihtout-queue';

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
        $this->info("Process started at: " . $startDateTime);
        Log::info("Process started at: " . $startDateTime);

        try {
            $cronRun = CronRunHistory::create([
                'cron_name' => 'process_cached_archived_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ])->_id;


            // Get all cache keys for API data
            $cacheKeys = VehicleArchivedApiData::orderBy('created_at', 'asc')->limit(100)->get();
            // Extract IDs of the fetched records

            if (count($cacheKeys) == 0) {
                $this->info('No Data For Process Cached Archived Data');
                return;
            }
        } catch (\Exception $e) {
            $this->error("Error fetching cache keys or updating status: " . $e->getMessage());

            CronRunHistory::where('_id', $cronRun)->update([
                'end_time' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // Send email notification
            $cronJobName = 'process_cached_archived_data';
            $adminEmails = explode(',', env('ADMIN_EMAIL'));
            Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
            return; // Exit to prevent further processing
        }

        foreach ($cacheKeys as $keyItem) {
            Log::info('Process Cached Archived Data Job Started For: ' . $keyItem->id);
            try {
                // Retrieve data from cache
                $data = $keyItem->cache_value;
                if (!$data) {
                    Log::info("No data found in cache for key: {$keyItem->id}");
                    return;
                }

                collect($data)->chunk(200)->each(function ($chunk) {
                    $batchData = $chunk->map(function ($car) {
                        return $this->prepareArchivedData((array) $car);
                    })->toArray();

                    VehicleProcessCachedArchivedApiData::insert([
                        'cache_value' => $batchData,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'expires_at' => Carbon::now()->addDays(7)->toDateTimeString(),
                    ]);
                });

                // Log success and remove cache
                Log::info("Data for cache key '{$keyItem->id}' processed successfully.");

                // Delete the cache key from the table
                VehicleArchivedApiData::where('cache_key', $keyItem->id)->delete();

            } catch (\Exception $e) {
                // Log any errors encountered during processing
                Log::info("Error processing data for cache key {$keyItem->id}: " . $e->getMessage());

            }
        }

        // Mark cron as successful
        CronRunHistory::where('_id', $cronRun)->update([
            'end_time' => now(),
            'status' => 'success',
            'updated_at' => now(),
        ]);
    }


    private function prepareArchivedData(array $car)
    {
        $data = [
            'lot_id' => $car['lot'],
            'status_id' => $car['status']['id'],
            'bid' => $car['bid'],
            'final_bid_updated_at' => $car['final_bid_updated_at'],
        ];
        return $data;
    }

}
