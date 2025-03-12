<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedArchivedDataJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use Illuminate\Support\Facades\Log;

class ProcessCachedArchivedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-archived-data';

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

        try {
            $cronRun = DB::connection('mysql')->table('cron_run_history')->insertGetId([
                'cron_name' => 'process_cached_archived_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Get all cache keys for API data
            $cacheKeys = DB::connection('mysql_remote')->table('cache_keys')->where('cache_key', 'like', 'vehicle_archived_data%')
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->take(50)
                ->get();

            // Extract IDs of the fetched records
            $cacheKeyIds = $cacheKeys->pluck('id');

            if ($cacheKeyIds->isNotEmpty()) {
                // Update the status of the fetched records to 'progress'
                DB::connection('mysql_remote')->table('cache_keys')->whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
            }
        } catch (\Exception $e) {
            $this->error("Error fetching cache keys or updating status: " . $e->getMessage());
            DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // Send email notification
            $cronJobName = 'process_cached_archived_data';
            $adminEmails = explode(',', env('ADMIN_EMAIL'));
            Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
            return; // Exit to prevent further processing
        }

        foreach ($cacheKeys as $cacheKey) {
            Log::info('Process Cached Archived Data Job Started For: ' . $cacheKey->cache_key);
            ProcessCachedArchivedDataJob::dispatch($cacheKey);
        }

        // Mark cron as successful
        DB::table('cron_run_history')->where('id', $cronRun)->update([
            'end_time' => Carbon::now(),
            'status' => 'success',
            'updated_at' => now(),
        ]);
    }


}
