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

        try{
            // Remote Connection to KVM4.1

            $cronRunRecord = CronRunHistory::create([
                'cron_name' => 'process_cached_data_to_database',
                'start_time' => now(),
                'status' => 'running',
            ]);
            $cronRun = $cronRunRecord->_id ?? null;

            if (!VehicleProcessCachedApiData::exists()) {
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
        VehicleProcessCachedApiData::orderBy('_id')
            ->take(100) // Fetch only the first 100 rows
            ->chunkById(10, function ($cacheKeys) {
                foreach ($cacheKeys as $itemKey) {
                    ProcessCachedDataToDatabaseJob::dispatch($itemKey);
                }
            });

        if($cronRun){

            CronRunHistory::where('_id', new ObjectId($cronRun))->update([
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);
        }


    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        Log::error($errorMessage);
        $apiUrl = config('app.cron_history_api_url') . '/api/cron-run-histories';

        CronRunHistory::where('_id', new ObjectId($cronRun))->update([
            'end_time' => Carbon::now(),
            'status' => 'failed',
            'updated_at' => now(),
        ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }


}
