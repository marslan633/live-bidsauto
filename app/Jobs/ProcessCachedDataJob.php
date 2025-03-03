<?php

namespace App\Jobs;

use App\Models\CacheKey;
use App\Models\RemoteCacheKey;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessCachedDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $cacheKey;

    public function __construct(CacheKey|RemoteCacheKey $cacheKey)
    {
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $IS_KVM_TWO = config('app.is_kvm_two');
            $CacheModel = $IS_KVM_TWO ? RemoteCacheKey::class : CacheKey::class;

            $key = $this->cacheKey->cache_key;
            // IF KVM_TWO than Read it from Remote Redis
            // IF KVM_ONE than Read it from Default Redis
            $data = Cache::store($IS_KVM_TWO ? 'redis_cache' : 'redis')->get($key);


            if (!$data) {
                $CacheModel::where('cache_key', $key)->delete();
                return;
            }

            $processDataForCache = [];
            foreach ($data as $car) {
                $processDataForCache[] = convertAndStoreDataToRedis($car);
            }

            // Store processed data in Redis
            $cacheKey = 'vehicle_data_' . now()->format('Y_m_d_H_i_s');
            $expiresAt = now()->addMinutes(300);
            // IF KVM_TWO THAN READ IT FROM DEFAULT
            // IF KVM_ONE THAN READ IT FROM REMIVE
            Cache::store($IS_KVM_TWO ? 'redis' :'redis_cache')->put($cacheKey, json_encode($processDataForCache), $expiresAt);

            // Save cache details to the database
            // IF KVM_TWO THAN USE DEFAULT DATABASE CONNECTION
            // IF KVM_ONE THAN USE REMOTE DATABASE CONNECTION
            $RemoteCacheModel = $IS_KVM_TWO  ? CacheKey::class : RemoteCacheKey::class;

            $RemoteCacheModel::updateOrCreate(
                ['cache_key' => $cacheKey],
                ['status' => 'pending', 'expires_at' => $expiresAt]
            );

            // Remove cache key from DB and Redis
            $CacheModel::where('cache_key', $key)->delete();
            // IF KVM_TWO THAN REMOVE IT FROM REMOTE
            // IF KVM_ONE THAN REMOVE IT FROM DEFAULT
            Cache::store($IS_KVM_TWO ? 'redis_cache' :'redis')->forget($key);
        } catch (\Exception $e) {
            Log::error("Error processing key {$this->cacheKey->cache_key}: " . $e->getMessage());
        }
    }

}
