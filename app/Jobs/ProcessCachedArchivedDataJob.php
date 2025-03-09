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

class ProcessCachedArchivedDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $cacheKey;
    public function __construct($cacheKey)
    {
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $key = $this->cacheKey->cache_key;
            // Retrieve data from cache
            $data = Cache::store('redis_cache')->get($key);

            if (!$data) {
                Log::info("No data found in cache for key: {$key}");
                return;
            }

            $batchData = [];
            $batchSize = intval(config('app.batch_size')); // Default batch size 100

            foreach ($data as $car) {
                $batchData[] = $this->prepareArchivedData((array) $car);

                // Process batch when the limit is reached
                if (count($batchData) >= $batchSize) {
                    $this->insertBatch($batchData);
                    $batchData = []; // Reset batch
                }
            }

            // Process remaining batch if any
            if (!empty($batchData)) {
                $this->insertBatch($batchData);
            }

            // Log success and remove cache
            Log::info("Data for cache key '{$key}' processed successfully.");

            // Delete the cache key from the table
            DB::connection('mysql')->table('cache_keys')->where('cache_key', $key)->delete();

            // Remove processed data from cache
            Cache::store('redis_cache')->forget($key);
        } catch (\Exception $e) {
            // Log any errors encountered during processing
            Log::info("Error processing data for cache key {$key}: " . $e->getMessage());

            $this->cacheKey->update(['status' => 'pending']);

        }
    }

    private function prepareArchivedData(array $car)
    {
        return [
            'lot_id' => $car['lot'],
            'status_id' => $car['status']['id'],
            'bid' => $car['bid'],
            'final_bid_updated_at' => $car['final_bid_updated_at'],
        ];
    }

    /**
     * ✅ Insert batch of processed data
     */
    private function insertBatch(array $batchData)
    {
        try {
            if (empty($batchData)) {
                return;
            }

            DB::beginTransaction();

            // Extract lot IDs
            $lotIds = array_column($batchData, 'lot_id');

            // Fetch existing archived records by lot_id
            $existingRecords = DB::connection('mysql')->table('vehicle_record_archiveds')
                ->whereIn('lot_id', $lotIds)
                ->pluck('id', 'lot_id');

            // Separate new and update data
            $newRecords = [];
            $updatedRecords = [];
            $failedRecords = []; // ❌ Store records that failed

            foreach ($batchData as $record) {
                try{
                    if (isset($existingRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['lot_id']];
                        $record['updated_at'] = now();
                        $updatedRecords[] = $record;
                    } else {
                        // New record - insert
                        $record['created_at'] = now();
                        $newRecords[] = $record;
                    }
                }catch (\Exception $e) {
                    $failedRecords[] = $record;
                    Log::info("Skipping record due to error: " . $e->getMessage());
                }
            }

            // ✅ Bulk Insert New Records
            if (!empty($newRecords)) {
                DB::connection('mysql')->table('vehicle_record_archiveds')->insert($newRecords);
            }

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                DB::connection('mysql')->table('vehicle_record_archiveds')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
            }

            DB::commit();

            Log::info("Batch processed successfully with " . count($newRecords) . " new and " . count($updatedRecords) . " updated records.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Batch processing failed: " . $e->getMessage());
        }
    }



    private function processCachedArchivedData($car)
    {
        try {
            $lotId = $car['lot'];
            $status_id = $car['status']['id'];
            $bid = $car['bid'];
            $finalBidUpdatedAt = $car['final_bid_updated_at'];

            $archivedRecord =  DB::connection('mysql')->table('vehicle_record_archiveds')->where('lot_id', $lotId)->first();

            if ($archivedRecord) {
                $archivedRecord->update([
                    'status_id' => $status_id,
                    'bid' => $bid,
                    'final_bid_updated_at' => $finalBidUpdatedAt,
                ]);

                Log::info("Updated archived record for lot_id: {$lotId}");
                // Get the latest SaleAuctionHistory for this lot_id
                $latestSaleHistory = DB::connection('mysql')->table('sale_auction_histories')->where('lot_id', $lotId)
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
                    Log::info("No sale history found for lot_id: {$lotId}");
                }
            } else {
                Log::info("Archived record not found for lot_id: {$lotId}");
            }
        } catch (\Exception $e) {
            Log::info("Error updating archived record for lot_id: {$lotId} - " . $e->getMessage());
        }
    }

}
