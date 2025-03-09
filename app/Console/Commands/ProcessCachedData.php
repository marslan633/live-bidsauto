<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataJob;
use App\Jobs\ProcessCachedDataJobKVMTWO;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, CacheKey,
    RemoteCacheKey
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use Illuminate\Support\Facades\Log;

class ProcessCachedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-data';

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
    \Log::info("Process started at: " . $startDateTime);

    try {
        // DB::connection('mysql')->beginTransaction();
        // DB::connection('mysql_remote')->beginTransaction();
        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name' => 'process_cached_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lock the cache keys for update
        // IF KVM_TWO THAN USE REMOTE DATABSE CONNECTION
        // IF KVM_ONE THAN USE DEFAULT DATABASE CONNECTION
        $IS_KVM_TWO = config('app.is_kvm_two');
        $CacheModel = $IS_KVM_TWO === true ? DB::connection('mysql_remote')->table('cache_keys') : DB::connection('mysql')->table('cache_keys');
        Log::info('IS_KVM_TWO ' . config('app.is_kvm_two'));
        Log::info('Cache Model ' . $IS_KVM_TWO === true ? 'REMOTE_CACHE_KEY' : 'CACHE_KEY');
        $this->info('Cache Model ' . $IS_KVM_TWO === true ? 'REMOTE_CACHE_KEY' : 'CACHE_KEY');
        $this->info('IS_KVM_TWO type => ' . gettype(config('app.is_kvm_two')) . ' ' . config('app.is_kvm_two') === true ? 'Yes' : 'No');

        $keyName = $IS_KVM_TWO === true ? 'vehicle_process_data_' : 'vehicle_api_data_';
        $cacheKeys = $CacheModel->where('cache_key', 'like', $keyName.'%')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            // ->lockForUpdate()
            // ->skipLocked()
            ->take(30)
            ->get();

        if ($cacheKeys->isEmpty()) {
            $this->info("No pending cache keys found.");
            // DB::connection('mysql')->commit();
            // DB::connection('mysql_remote')->commit();
            return;
        }

        $cacheKeyIds = $cacheKeys->pluck('id')->toArray();

        // Update status in bulk
        $CacheModel->whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
        // DB::connection('mysql')->commit();
        // DB::connection('mysql_remote')->commit();
    } catch (\Exception $e) {
        // DB::connection('mysql')->rollBack();
        // DB::connection('mysql_remote')->rollBack();
        Log::info("Error fetching cache keys: ", ['data' => json_encode($e->getMessage())]);
        $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
        return;
    }


    foreach ($cacheKeys as $cacheKey) {
        $this->info('Cache Key Running: ' . $cacheKey->cache_key);
        try{
            $CacheModel = $IS_KVM_TWO === true ? DB::connection('mysql_remote')->table('cache_keys') : DB::connection('mysql')->table('cache_keys');

            $key = $cacheKey->cache_key;
            // IF KVM_TWO than Read it from Remote Redis
            // IF KVM_ONE than Read it from Default Redis
            $data = Cache::store($IS_KVM_TWO === true ? 'redis_cache' : 'redis')->get($key);

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
            // IF KVM_TWO THAN Write IT FROM DEFAULT
            // IF KVM_ONE THAN Write IT FROM REMIVE
            Cache::store($IS_KVM_TWO === true ? 'redis' : 'redis_cache')->put($cacheKey, json_encode($processDataForCache), $expiresAt);

            // Save cache details to the database
            // IF KVM_TWO THAN USE DEFAULT DATABASE CONNECTION
            // IF KVM_ONE THAN USE REMOTE DATABASE CONNECTION
            $RemoteCacheModel = $IS_KVM_TWO === true  ? DB::connection('mysql')->table('cache_keys') : DB::connection('mysql_remote')->table('cache_keys');
                Log::info('Remote Cache Model Job ' . $IS_KVM_TWO === true ? 'CACHE_KEY' : 'REMOTE_CACHE_KEY');

            $RemoteCacheModel->updateOrInsert(
                ['cache_key' => $cacheKey],
                ['status' => 'pending', 'expires_at' => $expiresAt]
            );

            // Remove cache key from DB and Redis
            $CacheModel->where('cache_key', $key)->delete();
            // IF KVM_TWO THAN REMOVE IT FROM REMOTE
            // IF KVM_ONE THAN REMOVE IT FROM DEFAULT
            Cache::store($IS_KVM_TWO === true ? 'redis_cache' :'redis')->forget($key);

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
