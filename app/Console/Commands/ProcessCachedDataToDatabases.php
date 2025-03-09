<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataToDatabaseJob;
use App\Jobs\TestJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Mail, Log, Bus};
use App\Mail\CronJobFailedMail;
use Illuminate\Bus\Batch;
use Throwable;

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
        Log::info("Process started at: " . $startDateTime);

        try {
            DB::connection('mysql')->getPdo(); // Check MySQL connection
            DB::connection('mysql_remote')->getPdo(); // Check Remote DB connection
        } catch (\Exception $e) {
            $this->error('Database connection failed:');
            Log::info("Database connection failed: ", ['exception' => json_encode($e->getMessage())]);
            return;
        }

        $cronRun = null;

        DB::connection('mysql')->beginTransaction();
        DB::connection('mysql_remote')->beginTransaction();
        try{
            $cronRun = DB::connection('mysql')->table('cron_run_history')->insertGetId([
                'cron_name' => 'process_cached_data_to_database',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Lock the cache keys for update
            $cacheKeys = DB::connection('mysql_remote')->table('cache_keys')->where('cache_key', 'like', 'vehicle_process_data%')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            // ->lockForUpdate()
            ->take(1)
            ->get();

            if ($cacheKeys->isEmpty()) {
                $this->info("No pending cache keys found.");
                DB::connection('mysql')->commit();
                DB::connection('mysql_remote')->commit();
                return;
            }

            $cacheKeyIds = $cacheKeys->pluck('id')->toArray();
              // Update status in bulk
            DB::connection('mysql_remote')->table('cache_keys')->whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
            DB::connection('mysql')->commit();
            DB::connection('mysql_remote')->commit();
            Log::info('Database Commit Done');

        }catch(\Exception $e){
            DB::connection('mysql')->rollBack();
            DB::connection('mysql_remote')->rollBack();
            Log::info('Rolle Back From Process Cahed To Database');
            if($cronRun !== null){
                $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            }
            return;
        }

        // **Batch processing setup**
        // Initialize an empty array to hold the jobs
        // $jobs = [];
        // Iterate over the cache keys and create jobs
        foreach ($cacheKeys as $cacheKey) {
            // TestJob::dispatch($cacheKey->id, $cacheKey->cache_key);
            $this->info('Data Starting Handover To Job Done ' . $cacheKey->cache_key);
            ProcessCachedDataToDatabaseJob::dispatch($cacheKey->id, $cacheKey->cache_key);
            $this->info('Data End Handover To Job Done ' . $cacheKey->cache_key);
            // $jobs[] = new ProcessCachedDataToDatabaseJob($cacheKey->id, $cacheKey->cache_key);
        }

        // // Dispatch the batch of jobs
        // Bus::batch($jobs)
        // ->finally(function (Batch $batch) use ($cronRun) {
        //     // This callback will be executed after the batch has finished executing
        //     // You can perform any necessary cleanup here
        //      if($cronRun){
        //         DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
        //             'end_time' => Carbon::now(),
        //             'status' => 'success',
        //             'updated_at' => now(),
        //         ]);
        //     }
        // })
        // ->dispatch();

        if($cronRun){
            DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);
        }

        // ...Jobs, finalJob

    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        Log::error($errorMessage);
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
