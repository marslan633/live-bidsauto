<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataToDatabaseJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\{ RemoteCacheKey};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;

class ProcessCachedDataToDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:process-cached-data-to-databases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process cached data into database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $this->info("Process started at: " . $startDateTime);
        \Log::info("Process started at: " . $startDateTime);

        DB::beginTransaction();
        try{
            $cronRun = DB::table('cron_run_history')->insertGetId([
                'cron_name' => 'process_cached_data_to_database',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Lock the cache keys for update
            $cacheKeys = RemoteCacheKey::where('cache_key', 'like', 'vehicle_data%')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            // ->lockForUpdate()
            ->take(10)
            ->get();

            if ($cacheKeys->isEmpty()) {
                $this->info("No pending cache keys found.");
                DB::commit();
                return;
            }

            $cacheKeyIds = $cacheKeys->pluck('id')->toArray();
              // Update status in bulk
            RemoteCacheKey::whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
            DB::commit();
        }catch(\Exception $e){
            DB::rollBack();
            $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            return;
        }

         // **Batch processing setup**
        $batchData = [];
        $batchSize = config('app.batch_size'); // Process in chunks of 1000
        foreach ($cacheKeys as $cacheKey) {
            ProcessCachedDataToDatabaseJob::dispatch($cacheKey->id, $cacheKey->cache_key)->onQueue('high');
        }

         // Insert any remaining data (if less than 1000)
        if (!empty($batchData)) {
            $this->insertBatch($batchData);
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
