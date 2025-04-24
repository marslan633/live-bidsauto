<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\VehicleRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexVehicleRecordsJob implements ShouldQueue
{
    use Queueable;

    // Adding public properties for configuration values (optional)
    protected $cronRun = null;
    protected $minutes;

    public function __construct()
    {
        $this->minutes = intval(config('app.elastic_store_time'));
    }

    public function handle()
    {
        $startDateTime = Carbon::now();
        Log::info("Index Vehicles Process started at: " . $startDateTime);

        $elasticsearch = app('Elasticsearch');
        $url = config('app.cron_history_api_url') . '/cron-run-histories';

        try {
            // Remote Connection to KVM4.1
            $cronRunResponse = Http::timeout(120)->retry(3, 1000)->get($url . '?name=process_vehicles_to_elasticsearch');

            if ($cronRunResponse->successful()) {
                $lastCron = $cronRunResponse->json();
                if ($lastCron && $lastCron['end_time']) {
                    $endTime = Carbon::parse($lastCron['end_time']);
                    $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));

                    if ($timeDifference > 20) {
                        $this->minutes = $timeDifference + 10;
                    } elseif ($timeDifference === 20) {
                        $this->minutes = $timeDifference + 5;
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
                $this->cronRun = $cronRunResponse->json()['id'] ?? null;
            } else {
                Log::info('Error: STORE VEHICLES TO ELASTICSEARCH CREATED');
            }

        } catch (\Exception $e) {
            Log::info("Error: STORE VEHICLES TO ELASTICSEARCH: ", ['error' => $e->getMessage()]);
            return;
        }

        $minutes = Carbon::now()->subMinutes($this->minutes);

        if (config('app.is_full_fetch') == true) {
            VehicleRecord::chunk(500, function ($vehicles) use ($elasticsearch) {
                foreach ($vehicles as $vehicle) {
                    $elasticsearch->index([
                        'index' => 'vehicle_records',
                        'id' => $vehicle->id,
                        'body' => $vehicle->toArray(),
                    ]);
                }
            });
        } else {
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

        if ($this->cronRun) {
            $updateUrl = $url . "/$this->cronRun";
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

        Log::info('✅ Indexing vehicle_records completed!');
    }
}
