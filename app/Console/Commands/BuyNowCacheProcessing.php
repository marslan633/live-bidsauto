<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
                $cacheKey->update(['status' => 'pending']);
            }
        }
    }
}