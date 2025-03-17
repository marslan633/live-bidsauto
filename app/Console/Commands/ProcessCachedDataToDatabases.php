<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataToDatabaseJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Http, Mail, Log};
use App\Mail\CronJobFailedMail;
use App\Models\VehicleProcessCachedApiData;

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

    protected $apiUrl = 'https://your-first-server.com/api/cron-run-histories';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $this->info("Process started at: " . $startDateTime);
        Log::info("Process started at: " . $startDateTime);

        $cronRun = null;

        try{
            // Remote Connection to KVM4.1

            $responseCreateCronHistory = Http::post($this->apiUrl, [
                'cron_name' => 'process_cached_data_to_database',
                'start_time' => now(),
                'status' => 'running',
            ]);

            if ($responseCreateCronHistory->successful()) {
                $cronRun = $responseCreateCronHistory->json('id'); // Get inserted ID
                $this->info("Cron Run ID: " . $cronRun);
            } else {
                $this->info("Failed to store cron run history.");
            }

            $cacheKeys = VehicleProcessCachedApiData::orderBy('created_at', 'asc')->limit(100)->get();

            if (count($cacheKeys) == 0) {
                $this->info("No Data Pending to process");
            }


        }catch(\Exception $e){
            if($cronRun !== null){
                $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            }
            return;
        }

        // **Batch processing setup**
        // Initialize an empty array to hold the jobs
        // Iterate over the cache keys and create jobs
        foreach ($cacheKeys as $itemKey) {
            ProcessCachedDataToDatabaseJob::dispatch($itemKey);
        }

        if($cronRun){

            $responseUpdateCronRunHistory = Http::put($this->apiUrl."/$cronRun", [
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

            if ($responseUpdateCronRunHistory->successful()) {
                $this->info("Cron Run Updated Successfully.");
            } else {
                $this->info("Failed to update cron run history.");
            }
        }


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
            'updated_at' => now(),
        ]);

        $responseUpdateCronRunHistory = Http::put($this->apiUrl."/$cronRun", [
            'end_time' => Carbon::now(),
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        if ($responseUpdateCronRunHistory->successful()) {
            $this->info("Cron Run Updated Successfully.");
        } else {
            $this->info("Failed to update cron run history.");
        }

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }


}
