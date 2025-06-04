<?php

namespace App\Jobs\KvmThree;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftFutureBidsFromVehicleArchivedToVehicleRecordsJob implements ShouldQueue
{
    use Queueable;

    protected $records;

    public function __construct(array $records)
    {
        $this->queue = 'shift_future_bids_from_vehicle_archived_to_vehicle_records_job';
        $this->records = $records;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->insertBatch($this->records);
    }

    public function insertBatch(array $batchData)
    {
        Log::info('Starting Batch Insertion');
        $clientkvmOne = app('ElasticsearchKvmOne');
        try {
            if (empty($batchData)) {
                return;
            }


            // Extract API IDs from batchData
            $apiIds = array_column($batchData, 'api_id');

            // Fetch existing records by API ID
            $existingRecords = DB::table('vehicle_record_archiveds')->whereIn('api_id', $apiIds)->pluck('id', 'api_id');
            $idsToDelete = array_column($this->records, 'id');
            // Lists for new and updated records
            $newRecords = [];
            $updatedRecords = [];
            $failedRecords = []; // ❌ Store records that failed




            foreach ($batchData as $record) {
                try {
                    if (isset($existingRecords[$record['api_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['api_id']]; // Add ID for update
                        $record['processed_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        $updatedRecords[] = $record;
                    } else {
                        // New record - insert
                        $record['is_new'] = true;
                        $record['processed_at'] = Carbon::now();
                        $record['created_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        $newRecords[] = $record;
                    }
                } catch (\Exception $e) {
                    $failedRecords[] = $record;
                    $clientkvmOne->index([
                        'index' => 'error_logs',
                        'body' => [
                            'server_name' => 'KVM4.3',
                            'error_type' => 'Internal Server Error',
                            'command_name' => 'shift_future_bids_from_vehicle_archived_to_vehicle_records_job',
                            'error' => "Skipping record due to error: " . json_encode($e->getMessage()),
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }

            // ✅ Bulk Insert New Records
            if (!empty($newRecords)) {
                DB::table('vehicle_records')->insert($newRecords);
            }

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                foreach($updatedRecords as $item){
                    DB::table('vehicle_records')->where('id', $item['id'])->update($item);
                }
                // DB::table('vehicle_records')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
            }

            // Removing From Auction Histories
            foreach($batchData as $record){
                DB::connection('mysql')->table('sale_auction_histories')
                ->where([
                    'vin' => $record['vin'],
                    'lot_id' => $record['lot_id'],
                    'sale_date' => $record['sale_date'],
                ])->delete();

            }

            // Delete Archiveds Records
            DB::table('vehicle_record_archiveds')
            ->whereIn('id', $idsToDelete)
            ->delete();

        } catch (\Throwable $e) {
            $clientkvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'shift_future_bids_from_vehicle_archived_to_vehicle_records_job',
                    'error' => "Batch insert failed: " . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
            return;
        }


    }
}
