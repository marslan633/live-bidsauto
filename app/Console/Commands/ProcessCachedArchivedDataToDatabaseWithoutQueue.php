<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedArchivedDataJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleArchivedApiData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessCachedArchivedDataToDatabaseWithoutQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-archived-data-to-database-without-queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch data from cache and save it into the database';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $url = config('app.cron_history_api_url') . '/cron-run-histories';
        $apiUrl = config('app.cron_history_api_url') . '/get-archived-vehicles-for-database';

        $cronRun = null;

        try {

            // Remote Connection to KVM4.1
            $cronRunResponse = Http::timeout(120)->retry(3, 1000)->post($url, [
                'cron_name' => 'process_cached_archived_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($cronRunResponse->successful()) {
                Log::info('PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED');
                // Handle the successful API cronRunResponse
                $cronRun = $cronRunResponse->json()['id'] ?? null; // You can process the data as needed
                // Optionally, you can update the cron record with the API response or status
            } else {
                Log::info('Error: PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED');
            }


            // **Fetch Fresh Data from API**
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->get($apiUrl);

            if (!$response->successful()) {
                $this->info("Error In Fetch Archived Data Api Call");
                Log::info('Error: PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED FETCH API');
                return;
            }
            $data = $response->json()['data']['data'] ?? [];

            if (count($data) == 0) {
                $this->info("No Data Archived Pending to process");
                Log::info('NOT DATA:PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED');
                return;
            }

        } catch (\Exception $e) {
            Log::info("Error fetching cache keys: ", ['data' => json_encode($e->getMessage())]);
            $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            return;
        }



        collect($data)->chunk(100)->each(function ($chunk) {
            foreach ($chunk as $item) {
                // Dispatch a job for each item in the chunk
                $cacheKey = (object)$item;
                try {
                    // Retrieve data from cache
                    $data = unCompressData($cacheKey->cache_value);
                    Log::info('Uncompressed Log', ['data', json_encode($data)]);
                    if (!$data) {
                        Log::warning("No archived data found for key: {$cacheKey->id}");
                        return;
                    }

                    $batchData = [];

                    foreach ($data as $car) {
                        $batchData[] = $this->prepareArchivedData((array) $car);

                    }

                     // Process batch when the limit is reached
                     if (count($batchData) > 0) {
                        Log::info('Batch Start Insert');
                        $this->insertBatch($batchData, $cacheKey->id);
                        Log::info('Batch End Insert');
                        $batchData = []; // Reset batch
                    }

                    // Log success and remove cache
                    Log::info("Data for cache key '{$cacheKey->id}' processed successfully.");


                } catch (\Exception $e) {
                    // Log any errors encountered during processing
                    Log::info("Error processing data for cache key {$cacheKey->id}: " . $e->getMessage());

                }
            }
        });

        if($cronRun){

            $updateUrl = $url . "/$cronRun";
             // Remote Connection to KVM4.1
             $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->put($updateUrl, [
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

            if ($cronRunUpdateResponse->successful()) {
                Log::info('PROCESS CACHED DATA TO DATABASE UPDATED');
            } else {
                Log::info('ERROR: PROCESS CACHED DATA TO DATABASE UPDATED');


            }

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

    private function insertBatch(array $batchData, $cacheKey)
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


            // DB::commit();
            $url = config('app.cron_history_api_url') . "/delete-my-archive-vehicle" .'/'. $cacheKey;
            Log::info('DELET API URL: ', ['url' => $url]);
            $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->post($url, [
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

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        Log::error($errorMessage);

        $url = config('app.cron_history_api_url') . '/cron-run-histories';
        $updateUrl = $url . "/$cronRun";
        // Remote Connection to KVM4.1
        $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->put($updateUrl, [
                'end_time' => now(),
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        if ($cronRunUpdateResponse->successful()) {
            Log::info('PROCESS CACHED DATA TO DATABASE UPDATED FAILED');
        } else {
            Log::info('ERROR: PROCESS CACHED DATA TO DATABASE UPDATED FAILED');
        }

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_archived_data'));
    }



}
