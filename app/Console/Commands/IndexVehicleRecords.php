<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;


class IndexVehicleRecords extends Command
{
    protected $signature = 'index:vehicle-records';
    protected $description = 'Index all vehicle records to Elasticsearch';

    public function handle()
{
    $this->info('🚀 Indexing vehicle_records started...');

    $elasticsearch = app('Elasticsearch');

     // Only fetch records updated in the last 30 minutes
     $last30Minutes = Carbon::now()->subMinutes(30);

     VehicleRecord::where('updated_at', '>=', $last30Minutes)
         ->chunk(500, function ($vehicles) use ($elasticsearch) {
             foreach ($vehicles as $vehicle) {
                 $elasticsearch->index([
                     'index' => 'vehicle_records',
                     'id' => $vehicle->id,
                     'body' => $vehicle->toArray(),
                 ]);
             }
         });

    $this->info('✅ Indexing vehicle_records completed!');
}

}
