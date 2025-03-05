<?php

namespace App\Jobs;

use App\Models\RemoteCacheKey;
use App\Jobs\ProcessCacheKeyJob;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessCachedDataToDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $cacheKeyId;
    protected $cacheKey;

     /**
     * Create a new job instance.
     */
    public function __construct($cacheKeyId, $cacheKey)
    {
        $this->cacheKeyId = $cacheKeyId;
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
          try {
                $key = $this->cacheKey;
                $data = json_decode(Cache::store('redis')->get($key), true);
                if (!$data) {
                    Log::info("No data found for key: {$key}");
                    return;
                }
                $batchData = [];
                $batchSize = 1000;
                foreach ($data as $car) {
                    Log::info('Starting Batch Insert');
                    // **Process Data but Store in Batch**
                    $batchData[] = prepareCarData((array)$car);

                    // If batch reaches 1000, insert and reset
                    if (count($batchData) >= $batchSize) {
                        Log::info('Batch Inserted');
                        insertBatch($batchData);
                        $batchData = []; // Reset batch
                    }

                }

                if (!empty($batchData)) {
                    insertBatch($batchData);
                }


                // Remove cache key from DB and Redis
                RemoteCacheKey::where('id', $this->cacheKeyId)->delete();
                Cache::store('redis')->forget($key);

            } catch (\Exception $e) {
                RemoteCacheKey::find($this->cacheKey->id)->update(['status' => 'pending']);
                Log::error("Error processing key {$key}: " . $e->getMessage());
            }
    }
}
