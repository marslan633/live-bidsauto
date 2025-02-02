<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\{CacheKey, VehicleRecord};

class BuyNowCacheProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:cache-process-buy-now';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch data from cache and update the buy now price into the vehicle records table';

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
                'cron_name' => 'process_buy_now_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Fetch and update cache keys in a single query
            $cacheKeys = CacheKey::where('cache_key', 'like', 'buy_now_data%')
                                ->where('status', 'pending')
                                ->orderBy('created_at', 'asc')
                                ->take(50)
                                ->get();

            if ($cacheKeys->isEmpty()) {
                $this->info('No pending cache keys found.');
                return;
            }

            $cacheKeyIds = $cacheKeys->pluck('id');

            // Update status to 'progress' in a single query
            CacheKey::whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
        } catch (\Exception $e) {
            $this->error("Error fetching cache keys or updating status: " . $e->getMessage());
            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);
            return;
        }

        foreach ($cacheKeys as $cacheKey) {
            $key = $cacheKey->cache_key;
            $data = Cache::get($key);

            if (!$data) {
                $this->info("Cache key '{$key}' has no data or expired.");
                continue;
            }

            try {
                // Bulk update vehicles instead of looping individually
                $lotIds = collect($data)->pluck('lot')->toArray();

                // Fetch vehicles in a single query
                $vehicles = VehicleRecord::whereIn('lot_id', $lotIds)->get()->keyBy('lot_id');

                foreach ($data as $car) {
                    if (isset($vehicles[$car['lot']])) {
                        $vehicles[$car['lot']]->update(['buy_now' => $car['buy_now']['value']]);
                    }
                }

                // Log success
                $this->info("Processed cache key '{$key}' successfully.");

                // Delete cache key record and remove cache
                CacheKey::where('cache_key', $key)->delete();
                Cache::forget($key);
            } catch (\Exception $e) {
                // Handle errors properly
                $this->error("Error processing cache key '{$key}': " . $e->getMessage());
                \Log::error("Error processing cache key '{$key}': " . $e->getMessage());

                // Revert the status back to 'pending' in case of failure
                try {
                    $cacheKey->update(['status' => 'pending']);
                } catch (\Exception $updateError) {
                    $this->error("Failed to revert status for cache key '{$key}': " . $updateError->getMessage());
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
}