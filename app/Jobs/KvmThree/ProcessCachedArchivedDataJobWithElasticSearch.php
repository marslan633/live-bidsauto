<?php

namespace App\Jobs\KvmThree;

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
        $client = app('ElasticsearchKvmOne');
        try {
            // Retrieve data from cache
            $data = unCompressData($this->cacheKey->cache_value);
            // Log::info('Uncompressed Log', ['data', json_encode($data)]);
            if (!$data) {
                // $client->index([
                //     'index' => 'error_logs',
                //     'body' => [
                //         'server_name' => 'KVM4.3',
                //         'error_type' => 'General Error',
                //         'command_name' => 'cached_archived_data_queue_with_elasticsearch',
                //         'error' => 'No archived data found for key: ' . $this->cacheKey->_id,
                //         'created_at' => now()->toIso8601String(),
                //         'updated_at' => now()->toIso8601String(),
                //     ],
                // ]);
                return;
            }

            $batchData = [];

            foreach ($data as $car) {
                $preparedData = $this->prepareArchivedData((array) $car);
                if($preparedData){
                    $batchData[] = $preparedData;
                }
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
            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'cached_archived_data_queue_with_elasticsearch',
                    'error' => "Error processing data for cache key {$this->cacheKey->_id}: " . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);

        }
    }

    private function prepareArchivedData(array $car)
    {


        if (
            !is_null($car['lot']) &&
            !is_null($car['vin']) &&
            isset($car['status']['id']) && !is_null($car['status']['id']) &&
            !is_null($car['bid']) &&
            !is_null($car['final_bid_updated_at'])
        ) {
            return [
                'lot_id' => $car['lot'],
                'vin' => $car['vin'],
                'status_id' => $car['status']['id'],
                'bid' => $car['bid'],
                'final_bid_updated_at' => $car['final_bid_updated_at'],
            ];
        }

        return null;
    }

    private function insertBatch(array $batchData)
    {
        $client = app('ElasticsearchKvmOne');

        try {
            if (empty($batchData)) {
                return;
            }

            // DB::beginTransaction();

            // Extract lot IDs
            $vins = array_column($batchData, 'vin');
            $lotIds = array_column($batchData, 'lot_id');

            // Fetch existing records by vin
            $existingRecords = DB::table('vehicle_records')
                ->whereIn('vin', $vins)
                ->select('id', 'lot_id', 'sale_date', 'vin', 'odometer_mi', 'seller_id', 'domain_id')
                ->get()
                ->keyBy('lot_id');
            // Log::info('Vehicle Vin Records', ['existingRecords' => json_encode($existingRecords)]);

            $existingSaleRecords = DB::table('sale_auction_histories')
                ->whereIn('vin', $vins)
                ->orderBy('created_at', 'desc')
                ->pluck('id', 'lot_id');
            // Log::info('Vehicle Sale Records', ['existingSaleRecords' => json_encode($existingSaleRecords)]);
            // Separate new and update data

            $updatedRecords = [];
            $updatedRecordIds = [];
            $failedRecords = []; // ❌ Store records that failed
            $updatedSaleRecords = [];
            $newSaleRecords = [];
            foreach ($batchData as $record) {
                try{
                    Log::info('Lot Record', ['record' => json_encode($record)]);
                    Log::info('Lot Id'. $record['lot_id']);

                    if (isset($existingRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['lot_id']]->id;
                        Log::info('Record Id'. $record['id']);
                        $updatedRecordIds[] = $record['lot_id'];
                        $record['updated_at'] = now();
                        $updatedRecord = $record;
                        $updatedRecord['data_source'] = 2;
                        Log::info('updatedRecord', ['updatedRecord' => json_encode($updatedRecord)]);
                        $updatedRecords[] = $updatedRecord;
                        if (isset($existingSaleRecords[$record['lot_id']]) && isset($existingRecords[$record['lot_id']])) {
                            // Existing record - update full data
                            // Check If Record Exists or not
                            $checkExistingSaleAuctionHistoryRecord = DB::connection('mysql')->table('sale_auction_histories')->where([
                                'vin' => $record['vin'],
                                'lot_id' => $record['lot_id'],
                                'sale_date' => $existingRecords[$record['lot_id']]->sale_date
                            ])->first();

                            if(!is_null($checkExistingSaleAuctionHistoryRecord)){
                                $saleRecord = $record;
                                // vin, bid, lot_id, status_id, final_bid_updated_at
                                $saleRecord['id'] = $existingRecords[$record['lot_id']]->id;
                                $saleRecord['sale_date'] = $existingRecords[$record['lot_id']]->sale_date;
                                $saleRecord['odometer_mi'] = $existingRecords[$record['lot_id']]->odometer_mi;
                                $saleRecord['seller_id'] = $existingRecords[$record['lot_id']]->seller_id;
                                $saleRecord['updated_at'] = now();
                                $updatedSaleRecords[] = $saleRecord;
                            }else{
                                $newSaleRecord = $record;
                             // vin, bid, lot_id, status_id, final_bid_updated_at
                                $newSaleRecord['sale_date'] = $existingRecords[$record['lot_id']]->sale_date;
                                $newSaleRecord['odometer_mi'] = $existingRecords[$record['lot_id']]->odometer_mi;
                                $newSaleRecord['domain_id'] = $existingRecords[$record['lot_id']]->domain_id;
                                $newSaleRecord['seller_id'] = $existingRecords[$record['lot_id']]->seller_id;
                                $newSaleRecord['created_at'] = now();
                                $newSaleRecord['updated_at'] = now();
                                unset($newSaleRecord['id']); // ✅ Prevent duplicate primary key
                                Log::info('Sale Record 1', ['newSaleRecord' => json_encode($newSaleRecord)]);
                                $newSaleRecords[] = $newSaleRecord;
                            }
                        }elseif(!isset($existingSaleRecords[$record['lot_id']]) && isset($existingRecords[$record['lot_id']])){
                            $newSaleRecord = $record;
                             // vin, bid, lot_id, status_id, final_bid_updated_at
                            $newSaleRecord['sale_date'] = $existingRecords[$record['lot_id']]->sale_date;
                            $newSaleRecord['odometer_mi'] = $existingRecords[$record['lot_id']]->odometer_mi;
                            $newSaleRecord['domain_id'] = $existingRecords[$record['lot_id']]->domain_id;
                            $newSaleRecord['seller_id'] = $existingRecords[$record['lot_id']]->seller_id;
                            $newSaleRecord['created_at'] = now();
                            $newSaleRecord['updated_at'] = now();
                            unset($newSaleRecord['id']); // ✅ Prevent duplicate primary key
                            Log::info('Sale Record 2', ['newSaleRecord' => json_encode($newSaleRecord)]);
                            $newSaleRecords[] = $newSaleRecord;
                        }
                    }else{
                        Log::info('Lot Id not set');
                    }



                }catch (\Exception $e) {
                    $failedRecords[] = $record;
                    $client->index([
                        'index' => 'error_logs',
                        'body' => [
                            'server_name' => 'KVM4.3',
                            'error_type' => 'Internal Server Error',
                            'command_name' => 'cached_archived_data_queue_with_elasticsearch',
                            'error' => "Skipping record due to error: " . json_encode($e->getMessage()),
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }

            Log::info('updatedRecords', ['updatedRecords' => json_encode($updatedRecords)]);

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                Log::info('Not Empty');
                foreach($updatedRecords as $item_one){
                    DB::table('vehicle_records')->where('id', $item_one['id'])->update($item_one);
                    Log::info('Vehcile Archived Record Updated ' . $item_one['id'], ['data' => json_encode($item_one)]);
                }
            }else{
                Log::info('updatedRecords empty' . count($updatedRecords));
            }

            if (!empty($updatedSaleRecords)) {
                foreach($updatedSaleRecords as $item_two){
                    DB::table('sale_auction_histories')->where('id', $item_two['id'])->update($item_two);
                    Log::info('Sale Record Updated ' . $item_two['id'], ['data' => json_encode($item_two)]);
                }
            }

            if(!empty($newSaleRecords)){
                Log::info('New Sale Record', ['newSaleRecords' => json_encode($newSaleRecords)]);
                DB::table('sale_auction_histories')->insert($newSaleRecords);
            }

            try{

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
                        $client->index([
                            'index' => 'error_logs',
                            'body' => [
                                'server_name' => 'KVM4.3',
                                'error_type' => 'Internal Server Error',
                                'command_name' => 'cached_archived_data_queue_with_elasticsearch',
                                'error' => "⚠️ Failed to update document: " . json_encode($e->getMessage()),
                                'created_at' => now()->toIso8601String(),
                                'updated_at' => now()->toIso8601String(),
                            ],
                        ]);
                    }
                } else {
                    $client->index([
                        'index' => 'error_logs',
                        'body' => [
                            'server_name' => 'KVM4.3',
                            'error_type' => 'Internal Server Error',
                            'command_name' => 'cached_archived_data_queue_with_elasticsearch',
                            'error' => "⚠️ Document not found for update with _id: " . $this->cacheKey->_id,
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }catch (\Throwable $e) {
                $client->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'cached_archived_data_queue_with_elasticsearch',
                        'error' => "❌ Elasticsearch exists check failed: " . json_encode($e->getMessage()),
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }


            Log::info('Vehicle Process Cached Api Data Delete');
            Log::info("Batch processed successfully with " . count($updatedRecords) . " updated records.");
        } catch (\Exception $e) {
            // DB::rollBack();
            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'cached_archived_data_queue_with_elasticsearch',
                    'error' => "Batch processing failed:: " . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }
}
