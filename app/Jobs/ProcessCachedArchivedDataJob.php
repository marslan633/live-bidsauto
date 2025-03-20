<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessCachedArchivedDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $cacheKey;
    public function __construct($cacheKey)
    {
        $this->queue = "cached_archived_data_queue";
        $this->cacheKey = $cacheKey;
        Log::info('Process Cached Archived Constructr ');

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Process Cached Archived Data Job Handle Calling');

        try {
            // Retrieve data from cache
            $data = unCompressData($this->cacheKey->cache_value);
            Log::info('Uncompressed Log', ['data', json_encode($data)]);
            if (!$data) {
                Log::warning("No archived data found for key: {$this->cacheKey->id}");
                return;
            }

            $batchData = [];

            foreach ($data as $car) {
                $batchData[] = $this->prepareArchivedData((array) $car);

            }

             // Process batch when the limit is reached
             if (count($batchData) > 0) {
                Log::info('Batch Start Insert');
                $this->insertBatch($batchData);
                Log::info('Batch End Insert');
                $batchData = []; // Reset batch
            }

            // Log success and remove cache
            Log::info("Data for cache key '{$this->cacheKey->id}' processed successfully.");


        } catch (\Exception $e) {
            // Log any errors encountered during processing
            Log::info("Error processing data for cache key {$this->cacheKey->id}: " . $e->getMessage());

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

    private function insertBatch(array $batchData)
    {
        try {
            if (empty($batchData)) {
                return;
            }

            // DB::beginTransaction();

            // Extract lot IDs
            $lotIds = array_column($batchData, 'lot_id');

            // Fetch existing archived records by lot_id
            $existingRecords = DB::table('vehicle_record_archiveds')
                ->whereIn('lot_id', $lotIds)
                ->pluck('id', 'lot_id');
            $existingSaleRecords = DB::table('sale_auction_histories')
                ->whereIn('lot_id', $lotIds)
                ->pluck('id');

            // Separate new and update data

            $updatedRecords = [];
            $updatedRecordIds = [];
            $failedRecords = []; // ❌ Store records that failed
            $updatedSaleRecords = [];

            foreach ($batchData as $record) {
                try{


                    if (isset($existingRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['lot_id']];
                        $updatedRecordIds[] = $record['lot_id'];
                        $record['updated_at'] = now();
                        $updatedRecords[] = $record;
                    }

                    if (isset($existingSaleRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        $saleRecord = $record;
                        $saleRecord['id'] = $existingSaleRecords[$record['lot_id']];
                        $saleRecord['status_id'] = $record['status_id'];
                        $saleRecord['bid'] = $record['status_id'];
                        $updatedSaleRecords[] = $saleRecord;
                    }

                }catch (\Exception $e) {
                    $failedRecords[] = $record;
                    Log::info("Skipping record due to error: " . $e->getMessage());
                }
            }

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                DB::table('vehicle_record_archiveds')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
                Log::info('Updated Records Ids', ['data' => json_encode($updatedRecordIds)]);
            }
            if (!empty($saleRecord)) {
                DB::table('sale_auction_histories')->upsert($saleRecord, ['id'], array_keys($saleRecord[0]));
                Log::info('Updated Records Sale Ids', ['data' => json_encode($updatedRecordIds)]);
            }


            // DB::commit();
            $url = config('app.cron_history_api_url') . "/delete/vehicle-archived-record/" . $this->cacheKey->id;
            Log::info('DELET API URL: ', ['url' => $url]);
            $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->delete($url, [
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

            if ($cronRunUpdateResponse->successful()) {
                Log::info('Vehicle Process Cached Api Data Delete');
            } else {
                Log::info('ERROR: Vehicle Process Cached Api Data Delete');
            }
            Log::info("Batch processed successfully with " . count($updatedRecords) . " updated records.");
        } catch (\Exception $e) {
            // DB::rollBack();
            Log::error("Batch processing failed: " . $e->getMessage());
        }
    }
}
