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

    DB::connection('mysql')->beginTransaction();
    try {
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
        $CacheModel = $IS_KVM_TWO ? DB::connection('mysql_remote')->table('cache_keys') : DB::connection('mysql')->table('cache_keys');
        Log::info('IS_KVM_TWO ' . config('app.is_kvm_two'));
        Log::info('Cache Model ' . $IS_KVM_TWO ? 'REMOTE_CACHE_KEY' : 'CACHE_KEY');
        $this->info('Cache Model ' . $IS_KVM_TWO ? 'REMOTE_CACHE_KEY' : 'CACHE_KEY');
        $this->info('IS_KVM_TWO type => ' . gettype(config('app.is_kvm_two')) . ' ' . config('app.is_kvm_two') === true ? 'Yes' : 'No');

        $keyName = $IS_KVM_TWO ? 'vehicle_process_data_' : 'vehicle_api_data_';
        $cacheKeys = $CacheModel->where('cache_key', 'like', $keyName.'%')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            // ->lockForUpdate()
            // ->skipLocked()
            ->take(30)
            ->get();

        if ($cacheKeys->isEmpty()) {
            $this->info("No pending cache keys found.");
            DB::connection('mysql')->commit();
            return;
        }

        $cacheKeyIds = $cacheKeys->pluck('id')->toArray();

        // Update status in bulk
        $CacheModel->whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
        DB::connection('mysql')->commit();
    } catch (\Exception $e) {
        DB::connection('mysql')->rollBack();
        $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
        return;
    }

    foreach ($cacheKeys as $cacheKey) {
            Log::info('Cache Key ' . $cacheKey->cache_key);
            $this->info('Cache Key ' . $cacheKey->cache_key);
            ProcessCachedDataJob::dispatch($cacheKey->id);
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
    \Log::error($errorMessage);
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
