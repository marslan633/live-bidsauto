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

    DB::beginTransaction();
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
        $CacheModel = $IS_KVM_TWO ? RemoteCacheKey::class : CacheKey::class;
        $cacheKeys = $CacheModel::where('cache_key', 'like', 'vehicle_data%')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            // ->lockForUpdate()
            // ->skipLocked()
            // ->take(1)
            ->get();

        if ($cacheKeys->isEmpty()) {
            $this->info("No pending cache keys found.");
            DB::commit();
            return;
        }

        $cacheKeyIds = $cacheKeys->pluck('id')->toArray();

        // Update status in bulk
        $CacheModel::whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
        return;
    }

    foreach ($cacheKeys as $cacheKey) {
        if($IS_KVM_TWO){
             ProcessCachedDataJobKVMTWO::dispatch($cacheKey);
        }else{
            ProcessCachedDataJob::dispatch($cacheKey);
        }
        // ->delay(now()->addSeconds(rand(1, 5)));
    }

    DB::table('cron_run_history')->where('id', $cronRun)->update([
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
    DB::table('cron_run_history')->where('id', $cronRun)->update([
        'end_time' => Carbon::now(),
        'status' => 'failed',
        'error_message' => $errorMessage,
        'updated_at' => now(),
    ]);

    $adminEmails = explode(',', env('ADMIN_EMAIL'));
    Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
}

}
