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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCachedDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $cacheKey;
    public $is_kvm_two;
    public function __construct(CacheKey $cacheKey)
    {
        $this->queue = 'process_cache_data_queue';
        $this->is_kvm_two = config('app.is_kvm_two');
        Log::info('Cache Model Job ' . $this->is_kvm_two === true ? 'REMOTE_CACHE_KEY' : 'CACHE_KEY');
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Handle Function Called Fro Job');
        try {
            Log::info('Handle Function Called INSIDE Try Job');

            // Read Modal
            $CacheModel = $this->is_kvm_two === true ? RemoteCacheKey::class : CacheKey::class;

            $key = $this->cacheKey->cache_key;
            // IF KVM_TWO than Read it from Remote Redis
            // IF KVM_ONE than Read it from Default Redis
            $data = Cache::store($this->is_kvm_two === true ? 'redis_cache' : 'redis')->get($key);
            Log::info($this->is_kvm_two === true ? 'redis_cache' : 'redis');

            if (!$data) {
                Log::info('Data Not Found In Redis Job');
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
            // IF KVM_TWO THAN Write IT FROM DEFAULT
            // IF KVM_ONE THAN Write IT FROM REMIVE
            Cache::store($this->is_kvm_two === true ? 'redis' :'redis_cache')->put($cacheKey, json_encode($processDataForCache), $expiresAt);

            // Save cache details to the database
            // IF KVM_TWO THAN USE DEFAULT DATABASE CONNECTION
            // IF KVM_ONE THAN USE REMOTE DATABASE CONNECTION
            $RemoteCacheModel = $this->is_kvm_two === true  ? CacheKey::class : RemoteCacheKey::class;
                Log::info('Remote Cache Model Job ' . $this->is_kvm_two === true ? 'CACHE_KEY' : 'REMOTE_CACHE_KEY');

            $RemoteCacheModel::updateOrCreate(
                ['cache_key' => $cacheKey],
                ['status' => 'pending', 'expires_at' => $expiresAt]
            );

            // Remove cache key from DB and Redis
            $CacheModel::where('cache_key', $key)->delete();
            // IF KVM_TWO THAN REMOVE IT FROM REMOTE
            // IF KVM_ONE THAN REMOVE IT FROM DEFAULT
            Cache::store($this->is_kvm_two === true ? 'redis_cache' :'redis')->forget($key);
        } catch (\Exception $e) {
            CacheKey::where('id',$this->cacheKey->id)->update(['status' => 'pending']);
            Log::error("Error processing key {$this->cacheKey->cache_key}: " . $e->getMessage());
        }
    }

}
