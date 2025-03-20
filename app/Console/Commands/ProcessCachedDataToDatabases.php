<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataToDatabaseJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Http, Mail, Log};
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
        $this->info("API URL " . config('app.cron_history_api_url'));
        Log::info("Process started at: " . $startDateTime);

        $cronRun = null;

        $url = config('app.cron_history_api_url') . '/cron-run-histories';
        $apiUrl = config('app.cron_history_api_url') . '/get-vehicles-for-database';

        try{
            // Remote Connection to KVM4.1
            $cronRunResponse = Http::timeout(120)->retry(3, 1000)->post($url, [
                'cron_name' => 'process_cached_data_to_database',
                'start_time' => now(),
                'status' => 'running',
            ]);

            if ($cronRunResponse->successful()) {
                Log::info('PROCESS CACHED DATA TO DATABASE CREATED');
                // Handle the successful API cronRunResponse
                $cronRun = $cronRunResponse->json()['id'] ?? null; // You can process the data as needed
                // Optionally, you can update the cron record with the API response or status
            } else {
                Log::info('Error: PROCESS CACHED DATA TO DATABASE CREATED');
            }


            // **Fetch Fresh Data from API**
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->get($apiUrl);

            if (!$response->successful()) {
                $this->info("Error In Fetch Data Api Call");
                Log::info('Error: PROCESS CACHED DATA TO DATABASE CREATED FETCH API');
                return;
            }
            $data = $response->json()['data']['data'] ?? [];

            if (count($data) == 0) {
                $this->info("No Data Pending to process");
                Log::info('NOT DATA:PROCESS CACHED DATA TO DATABASE CREATED');
                return;
            }

        }catch(\Exception $e){
            // if($cronRun !== null){
                $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            // }
            return;
        }

        // **Batch processing setup**
        // Initialize an empty array to hold the jobs
        collect($data)->chunk(100)->each(function ($chunk) {
            foreach ($chunk as $item) {
                // Dispatch a job for each item in the chunk
                ProcessCachedDataToDatabaseJob::dispatch($item);
            }
        });

        if($cronRun){

            $updateUrl = $url . "/$cronRun";
             // Remote Connection to KVM4.1
             $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->put($updateUrl, [
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

            if ($cronRunUpdateResponse->successful()) {
                Log::info('PROCESS CACHED DATA TO DATABASE UPDATED');
            } else {
                Log::info('ERROR: PROCESS CACHED DATA TO DATABASE UPDATED');


            }

        }


    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        Log::error($errorMessage);
        $url = config('app.cron_history_api_url') . '/cron-run-histories';
        $updateUrl = $url . "/$cronRun";
             // Remote Connection to KVM4.1
             $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->put($updateUrl, [
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'updated_at' => now(),
            ]);

            if ($cronRunUpdateResponse->successful()) {
                Log::info('PROCESS CACHED DATA TO DATABASE UPDATED FAILED');
            } else {
                Log::info('ERROR: PROCESS CACHED DATA TO DATABASE UPDATED FAILED');
            }


        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }


}
