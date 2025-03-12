<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCachedDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $cacheKey;
    public function __construct($cacheKey)
    {
        $this->queue = 'process_cache_data_queue';
        $keyData = DB::connection('mysql')->table('cache_keys')->where('id', $cacheKey)->first();
        Log::info('Cache Key Data From Job', ['keyData' => json_encode($keyData)]);
        $this->cacheKey = $keyData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Handle Call');

        try {

            // Read Modal
            $CacheModel = DB::connection('mysql')->table('cache_keys');

            $key = $this->cacheKey->cache_key;

            $data = $this->cacheKey->cache_value;


            if (!$data) {
                Log::info('Deleted Job Key');
                return;
            }

            $processDataForCache = [];
            foreach ($data as $car) {
                $processedCar = convertAndStoreDataToRedis($car);
                $processDataForCache[] = $processedCar;
            }

            // Store processed data in Redis
            $cacheKey = 'vehicle_process_data_' . now()->format('Y_m_d_H_i_s');
            $expiresAt = now()->addMinutes(intval(config('app.cache_key_expiry')));


            // Save cache details to the cache database
            $RemoteCacheModel = DB::connection('mysql_remote')->table('cache_keys');

            $RemoteCacheModel->updateOrInsert(
                ['cache_key' => $cacheKey],
                [
                    'status' => 'pending',
                    'cache_value' => $processDataForCache,
                    'expires_at' => $expiresAt,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );

            // Remove cache key from DB and Redis
            $CacheModel->where('cache_key', $key)->delete();

        } catch (\Exception $e) {
            DB::connection('mysql')->table('cache_keys')->where('id',$this->cacheKey->id)->update(['status' => 'pending']);
            Log::info("Error processing key {$this->cacheKey->cache_key}: " . $e->getMessage());
        }
    }

}
