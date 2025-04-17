<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexVehicleRecords extends Command
{
    protected $signature = 'index:vehicle-records';
    protected $description = 'Index all vehicle records to Elasticsearch';

    public function handle()
{
    $startDateTime = Carbon::now();
    $this->info("Index Vehicles Process started at: " . $startDateTime);
    $this->info("API URL " . config('app.cron_history_api_url'));
    Log::info("Index Vehicles Process started at: " . $startDateTime);

    $elasticsearch = app('Elasticsearch');

     // Only fetch records updated in the last 30 minutes
     $minutes = intval(config('app.elastic_store_time'));
     $minutes = Carbon::now()->subMinutes($minutes);


     $cronRun = null;

     $url = config('app.cron_history_api_url') . '/cron-run-histories';
     $apiUrl = config('app.cron_history_api_url') . '/get-vehicles-for-database';


     try{
        // Remote Connection to KVM4.1
        $cronRunResponse = Http::timeout(120)->retry(3, 1000)->get($url, [
            'name' => 'process_vehicles_to_elasticsearch'
        ]);

        if ($cronRunResponse->successful()) {
            $lastCron = $cronRunResponse->json();
            if ($lastCron && $lastCron->end_time) {
                $endTime = Carbon::parse($lastCron->end_time);
                $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));

                if ($timeDifference > 20) {
                    $minutes = $timeDifference + 10;
                } elseif ($timeDifference === 20) {
                    $minutes = $timeDifference + 5;
                }
            }
        }

        $cronRunResponse = Http::timeout(120)->retry(3, 1000)->post($url, [
            'cron_name' => 'process_vehicles_to_elasticsearch',
            'start_time' => now(),
            'status' => 'running',
        ]);

        if ($cronRunResponse->successful()) {
            Log::info('STORE VEHICLES TO ELASTICSEARCH CREATED');
            // Handle the successful API cronRunResponse
            $cronRun = $cronRunResponse->json()['id'] ?? null; // You can process the data as needed
            // Optionally, you can update the cron record with the API response or status
        } else {
            Log::info('Error: STORE VEHICLES TO ELASTICSEARCH CREATED');
        }

    }catch(\Exception $e){
        // if($cronRun !== null){
            $this->handleCronError($cronRun, "Error: STORE VEHICLES TO ELASTICSEARCH: " . $e->getMessage());
        // }
        return;
    }

    if(config('app.is_full_fetch') == true){
         VehicleRecord::chunk(500, function ($vehicles) use ($elasticsearch) {
             foreach ($vehicles as $vehicle) {
                 $elasticsearch->index([
                     'index' => 'vehicle_records',
                     'id' => $vehicle->id,
                     'body' => $vehicle->toArray(),
                 ]);
             }
         });
    }else{
        VehicleRecord::where('updated_at', '>=', $minutes)
        ->chunk(500, function ($vehicles) use ($elasticsearch) {
            foreach ($vehicles as $vehicle) {
                $elasticsearch->index([
                    'index' => 'vehicle_records',
                    'id' => $vehicle->id,
                    'body' => $vehicle->toArray(),
                ]);
            }
        });
    }

    if($cronRun){

        $updateUrl = $url . "/$cronRun";
         // Remote Connection to KVM4.1
         $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->put($updateUrl, [
            'end_time' => Carbon::now(),
            'status' => 'success',
            'updated_at' => now(),
        ]);

        if ($cronRunUpdateResponse->successful()) {
            Log::info('STORE VEHICLES TO ELASTICSEARCH CREATED');
        } else {
            Log::info('ERROR: STORE VEHICLES TO ELASTICSEARCH CREATED');


        }

    }

    $this->info('✅ Indexing vehicle_records completed!');
}

}
