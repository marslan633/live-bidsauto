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

class ProcessCachedArchivedDataJobWithElasticSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $cacheKey;
    public function __construct($cacheKey)
    {
        $this->queue = "cached_archived_data_queue_with_elasticsearch";
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
                Log::warning("No archived data found for key: {$this->cacheKey->_id}");
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
            Log::info("Data for cache key '{$this->cacheKey->_id}' processed successfully.");


        } catch (\Exception $e) {
            // Log any errors encountered during processing
            Log::info("Error processing data for cache key {$this->cacheKey->_id}: " . $e->getMessage());

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
                foreach($updatedRecords as $item_one){

                    DB::table('vehicle_record_archiveds')->where('id', $item_one['id'])->update($item_one);
                }
                // DB::table('vehicle_record_archiveds')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
                Log::info('Updated Records Ids', ['data' => json_encode($updatedRecordIds)]);
            }
            if (!empty($saleRecord)) {
                foreach($saleRecord as $item_two){
                    DB::table('sale_auction_histories')->where('id', $item_two['id'])->update($item_two);
                }
                // DB::table('sale_auction_histories')->upsert($saleRecord, ['id'], array_keys($saleRecord[0]));
                Log::info('Updated Records Sale Ids', ['data' => json_encode($updatedRecordIds)]);
            }



            try{
                $client = app('ElasticsearchKvmOne');

                $response = $client->exists([
                    'index' => 'vehicle_archived_api_data',
                    'id' => $this->cacheKey->_id,
                ]);

                if ($response) {
                    try{
                        $client->update([
                            'index' => 'vehicle_archived_api_data',
                            'id' => $this->cacheKey->_id,
                            'body' => [
                                'doc' => [
                                    'status' => 'completed'
                                ]
                            ]
                        ]);
                        Log::info("✅ Elasticsearch Processed document status updated for _id: " . $this->cacheKey->_id);
                    }  catch (\Throwable $e) {
                        Log::warning("⚠️ Failed to update document: " . $e->getMessage());
                    }
                } else {
                    Log::warning("⚠️ Document not found for update with _id: " . $this->cacheKey->_id);
                }
            }catch (\Throwable $e) {
                Log::error("❌ Elasticsearch exists check failed: " . $e->getMessage());
            }


            Log::info('Vehicle Process Cached Api Data Delete');
            Log::info("Batch processed successfully with " . count($updatedRecords) . " updated records.");
        } catch (\Exception $e) {
            // DB::rollBack();
            Log::error("Batch processing failed: " . $e->getMessage());
        }
    }
}
