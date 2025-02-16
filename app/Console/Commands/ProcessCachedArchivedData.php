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

class ProcessCachedArchivedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-archived-data';

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
        \Log::info("Process started at: " . $startDateTime);

        try {
            $cronRun = DB::table('cron_run_history')->insertGetId([
                'cron_name' => 'process_cached_archived_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Get all cache keys for API data
            $cacheKeys = CacheKey::where('cache_key', 'like', 'vehicle_archived_data%')
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->take(50)
                ->get();

            // Extract IDs of the fetched records
            $cacheKeyIds = $cacheKeys->pluck('id');

            if ($cacheKeyIds->isNotEmpty()) {
                // Update the status of the fetched records to 'progress'
                CacheKey::whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
            }
        } catch (\Exception $e) {
            $this->error("Error fetching cache keys or updating status: " . $e->getMessage());
            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
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

        foreach ($cacheKeys as $cacheKey) {
            $key = $cacheKey->cache_key;

            try {
                // Retrieve data from cache
                $data = Cache::get($key);

                if (!$data) {
                    $this->warning("No data found in cache for key: {$key}");
                    \Log::info("No data found in cache for key: {$key}");

                    // Remove the cache key from the database
                    CacheKey::where('cache_key', $key)->delete();
                    continue;
                }

                // Process each car data
                foreach ($data as $car) {
                    $this->processCachedArchivedData($car);
                }

                // Log success and remove cache
                $this->info("Data for cache key '{$key}' processed successfully.");

                // Delete the cache key from the table
                CacheKey::where('cache_key', $key)->delete();

                // Remove processed data from cache
                Cache::forget($key);
            } catch (\Exception $e) {
                // Log any errors encountered during processing
                $this->error("Error processing data for cache key {$key}: " . $e->getMessage());

                try {
                    // Optionally revert the status to 'pending' on failure
                    $cacheKey->update(['status' => 'pending']);
                } catch (\Exception $updateError) {
                    $this->error("Failed to revert status for cache key {$key}: " . $updateError->getMessage());
                }
            }
        }

        // Mark cron as successful
        DB::table('cron_run_history')->where('id', $cronRun)->update([
            'end_time' => Carbon::now(),
            'status' => 'success',
            'updated_at' => now(),
        ]);
    }

    private function processCachedArchivedData($car)
    {
        try {
            $lotId = $car['lot'];
            $status_id = $car['status']['id'];
            $bid = $car['bid'];
            $finalBidUpdatedAt = $car['final_bid_updated_at'];

            $archivedRecord = VehicleRecordArchived::where('lot_id', $lotId)->first();

            if ($archivedRecord) {
                $archivedRecord->update([
                    'status_id' => $status_id,
                    'bid' => $bid,
                    'final_bid_updated_at' => $finalBidUpdatedAt,
                ]);

                Log::info("Updated archived record for lot_id: {$lotId}");
                // Get the latest SaleAuctionHistory for this lot_id
                $latestSaleHistory = SaleAuctionHistory::where('lot_id', $lotId)
                    ->orderByDesc('sale_date') // Assuming sale_date is used to determine the latest entry
                    ->first();

                if ($latestSaleHistory) {
                    // Update the latest SaleAuctionHistory record
                    $latestSaleHistory->update([
                        'status_id' => $status_id,
                        'bid' => $bid,
                    ]);

                    Log::info("Updated latest sale history for lot_id: {$lotId}");
                } else {
                    Log::warning("No sale history found for lot_id: {$lotId}");
                }
            } else {
                Log::warning("Archived record not found for lot_id: {$lotId}");
            }
        } catch (Exception $e) {
            Log::error("Error updating archived record for lot_id: {$lotId} - " . $e->getMessage());
        }
    }
}
