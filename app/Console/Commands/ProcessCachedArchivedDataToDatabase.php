<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedArchivedDataJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use App\Models\CronRunHistory;
use App\Models\VehicleArchivedApiData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessCachedArchivedDataToDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-archived-data-to-database';

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
        $url = config('app.cron_history_api_url') . '/cron-run-histories';
        $apiUrl = config('app.cron_history_api_url') . '/get-archived-vehicles-for-database';

        $cronRun = null;

        try {

            // Remote Connection to KVM4.1
            $cronRunResponse = Http::timeout(120)->retry(3, 1000)->post($url, [
                'cron_name' => 'process_cached_archived_data',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($cronRunResponse->successful()) {
                Log::info('PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED');
                // Handle the successful API cronRunResponse
                $cronRun = $cronRunResponse->json()['id'] ?? null; // You can process the data as needed
                // Optionally, you can update the cron record with the API response or status
            } else {
                Log::info('Error: PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED');
            }


            // **Fetch Fresh Data from API**
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->get($apiUrl);

            if (!$response->successful()) {
                $this->info("Error In Fetch Archived Data Api Call");
                Log::info('Error: PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED FETCH API');
                return;
            }
            $data = $response->json()['data']['data'] ?? [];

            if (count($data)) {
                $this->info("No Data Archived Pending to process");
                Log::info('NOT DATA:PROCESS CACHED ARCHIVED DATA TO DATABASE CREATED');
                return;
            }

        } catch (\Exception $e) {
            Log::info("Error fetching cache keys: ", ['data' => json_encode($e->getMessage())]);
            $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            return;
        }



        collect($data)->chunk(100)->each(function ($chunk) {
            foreach ($chunk as $item) {
                // Dispatch a job for each item in the chunk
                ProcessCachedArchivedDataJob::dispatch($item);
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
                'end_time' => now(),
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        if ($cronRunUpdateResponse->successful()) {
            Log::info('PROCESS CACHED DATA TO DATABASE UPDATED FAILED');
        } else {
            Log::info('ERROR: PROCESS CACHED DATA TO DATABASE UPDATED FAILED');
        }

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_archived_data'));
    }



}
