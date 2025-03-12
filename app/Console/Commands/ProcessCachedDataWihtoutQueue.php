<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
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
        $this->info("Process started at: " . $startDateTime);
        Log::info("Process started at: " . $startDateTime);

        $CacheModel = DB::connection('mysql')->table('cache_keys');

        try {

            // Always Will Run On Default Server
            $cronRun = DB::connection('mysql')->table('cron_run_history')->insertGetId([
                'cron_name' => 'process_cached_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cacheKeys = $CacheModel->where('cache_key', 'like', 'vehicle_api_data_%')
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->take(15)
                ->get();

            if ($cacheKeys->isEmpty()) {
                $this->info("No pending cache keys found.");
                DB::connection('mysql')->commit();
                return;
            }

            $cacheKeyIds = $cacheKeys->pluck('id')->toArray();

            // Update status in bulk
            $CacheModel->whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
        } catch (\Exception $e) {
            Log::info("Error fetching cache keys: ", ['data' => json_encode($e->getMessage())]);
            $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            return;
        }


        foreach ($cacheKeys as $cacheKey) {
            $this->info('Cache Key Running: ' . $cacheKey->cache_key);
            // ProcessCachedDataJob::dispatch($cacheKey->cache_key);
            try{

                $key = $cacheKey->cache_key;
                $data = json_decode($cacheKey->cache_value, true);

                if (!$data) {
                    Log::info('Data Not Found');
                    $this->info('Data not found');
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

                // Save cache details to the database
                $RemoteCacheModel = DB::connection('mysql_remote')->table('cache_keys');

                $RemoteCacheModel->updateOrInsert(
                    ['cache_key' => $cacheKey],
                    [
                        'status' => 'pending',
                        'cache_value' => $processDataForCache,
                        'expires_at' => $expiresAt,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]
                );

                // Remove cache key from DB and Redis
                $CacheModel->where('cache_key', $key)->delete();

                $this->info('Key Stored: '. $key);

            }catch(\Exception $e){
                DB::connection('mysql')->table('cache_keys')->where('id',$cacheKey->id)->update(['status' => 'pending']);
                $this->info("Error processing key {$cacheKey->cache_key}: ");
                Log::info("Error processing key {$cacheKey->cache_key}: " . $e->getMessage());
            }
        }

        DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
            'end_time' => Carbon::now(),
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
        DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
            'end_time' => Carbon::now(),
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }

}
