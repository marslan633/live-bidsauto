<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleApiData;
use App\Models\VehicleProcessCachedApiData;
use Illuminate\Support\Facades\Log;

class ProcessCachedDataWihtoutQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-data-without-queue';

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
        // $this->info("Process started at: " . $startDateTime);
        // Log::info("Process started at: " . $startDateTime);

        try {

            // Always Will Run On Default Server
            $cronRun = CronRunHistory::create([
                'cron_name' => 'process_cached_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ])->_id;

            $cacheKeys = VehicleApiData::orderBy('created_at', 'desc')->limit(200)->get();
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
                $data = unCompressData($keyItem->cache_value);
                $this->info(gettype($data));
                // Log::info('Reading Cached Data', ['cache_value' => json_encode($data)]);

                if (!$data) {
                    // Log::info('Data Not Found');
                    $this->info('Data not found');
                    return;
                }

                $processDataForCache = [];
                foreach ($data as $car) {
                    $processedCar = convertAndStoreDataToRedis($car);
                    $processDataForCache[] = $processedCar;
                }

                VehicleProcessCachedApiData::insert([
                    'cache_value' => compressData($processDataForCache),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'expires_at' => Carbon::now()->addDays(7)
                ]);

                // Remove cache key from DB and Redis
                VehicleApiData::where('id', $keyItem->id)->delete();
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
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }

}
