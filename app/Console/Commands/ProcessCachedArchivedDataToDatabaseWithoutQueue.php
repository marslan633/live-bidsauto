<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleArchivedApiData;
use Illuminate\Support\Facades\Log;

class ProcessCachedArchivedDataToDatabaseWithoutQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-archived-data-to-database-wihtout-queue';

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

        try {

            // Always Will Run On Default Server
            $cronRun = CronRunHistory::create([
                'cron_name' => 'process_cached_archived_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ])->_id;



            $cacheKeys = VehicleArchivedApiData::orderBy('created_at', 'asc')->limit(100)->get();
            if (count($cacheKeys) == 0) {
                $this->info("No pending cache keys found.");
                return;
            }

        } catch (\Exception $e) {
            Log::info("Error fetching cache keys: ", ['data' => json_encode($e->getMessage())]);
            $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            return;
        }

        foreach ($cacheKeys as $keyItem) {
            $this->info('ruuning loop');
            try{
                $data = $keyItem->cache_value;
                $this->info(gettype($data));
                // Log::info('Reading Cached Data', ['cache_value' => json_encode($data)]);

                if (!$data) {
                    // Log::info('Data Not Found');
                    $this->info('Data not found');
                    return;
                }

                $processDataForCache = [];
                foreach ($data as $car) {
                    $processedCar =  $this->prepareArchivedData((array) $car);
                    Log::info('processedCar', ['processedCar' => json_encode($processedCar)]);
                    $processDataForCache[] = $processedCar;
                }

                    Log::info('Batch Start Insert');
                    $this->insertBatch($processDataForCache);
                    Log::info('Batch End Insert');
                    $processDataForCache = [];

                // Remove cache key from DB and Redis
                VehicleArchivedApiData::where('id', $keyItem->id)->delete();
                // $this->info('Key Stored: '. $keyItem->id);

            }catch(\Exception $e){
                // $this->info("Error processing key {$keyItem->cache_key}: ");
                Log::info("Error processing key {$keyItem->cache_key}: " . $e->getMessage());
            }
        }


        CronRunHistory::where('_id', $cronRun)->update([
            'end_time' => now(),
            'status' => 'success',
            'updated_at' => now(),
        ]);

    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        Log::error($errorMessage);
        // Always Run on Defautl Server
        CronRunHistory::where('_id', $cronRun)->update([
            'end_time' => now(),
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_archived_data'));
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

            // Separate new and update data

            $updatedRecords = [];
            $failedRecords = []; // ❌ Store records that failed

            foreach ($batchData as $record) {
                try{
                    if (isset($existingRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['lot_id']];
                        $record['updated_at'] = now();
                        $updatedRecords[] = $record;
                    }
                }catch (\Exception $e) {
                    $failedRecords[] = $record;
                    Log::info("Skipping record due to error: " . $e->getMessage());
                }
            }

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                DB::table('vehicle_record_archiveds')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
            }

            // DB::commit();

            Log::info("Batch processed successfully with " . count($updatedRecords) . " updated records.");
        } catch (\Exception $e) {
            // DB::rollBack();
            Log::error("Batch processing failed: " . $e->getMessage());
        }
    }

}
