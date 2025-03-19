<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataToDatabaseJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Http, Mail, Log};
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleProcessCachedApiData;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;

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
        $cronRun = '';

        $url = config('app.cron_history_api_url') . '/cron-run-histories';
        $apiUrl = config('app.cron_history_api_url') . '/get-vehicles-for-database';

        try{
            // Remote Connection to KVM4.1
            $cronRunResponse = Http::post($url, [
                'cron_name' => 'process_cached_data_to_database',
                'start_time' => now(),
                'status' => 'running',
            ]);

            if ($cronRunResponse->successful()) {
                // Handle the successful API cronRunResponse
                $cronRun = $cronRunResponse->json()['id'] ?? null; // You can process the data as needed
                // Optionally, you can update the cron record with the API response or status
            } else {
                Log::info('Create Cron Run History Not Working');
            }


            // **Fetch Fresh Data from API**
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->get($apiUrl);

            if (!$response->successful()) {
                $this->info("Error In Fetch Data Api Call");
                $this->info("Error In Fetch Data Api Call");
                return;
            }
            $data = $response->json()['data'] ?? [];

            if (count($data)) {
                $this->info("No Data Pending to process");
                $this->info("No Data Pending to process");
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
        collect($data)->chunk(10)->each(function ($chunk) {
            foreach ($chunk as $item) {
                // Dispatch a job for each item in the chunk
                ProcessCachedDataToDatabaseJob::dispatch($item);
            }
        });

        if($cronRun){

            $updateUrl = $url . "/$cronRun";
             // Remote Connection to KVM4.1
             $cronRunUpdateResponse = Http::post($updateUrl, [
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

            if ($cronRunUpdateResponse->successful()) {
                Log::info('Cron Run History Updating Successfully!');
            } else {
                Log::info('Update Cron Run History Not Working');
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
             $cronRunUpdateResponse = Http::post($updateUrl, [
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'updated_at' => now(),
            ]);

            if ($cronRunUpdateResponse->successful()) {
                Log::info('Cron Run History Updating Successfully!');
            } else {
                Log::info('Update Cron Run History Not Working');
            }


        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }


}
