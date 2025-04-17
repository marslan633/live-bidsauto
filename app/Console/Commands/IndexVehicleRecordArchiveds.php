<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleRecordArchived;
use Carbon\Carbon;


class IndexVehicleRecordArchiveds extends Command
{
    protected $signature = 'index:vehicle-record-archiveds';
    protected $description = 'Index all vehicle records to Elasticsearch';

    public function handle()
{
    $this->info('🚀 Indexing vehicle_record_archiveds started...');

    $elasticsearch = app('Elasticsearch');
    $minutes = intval(config('app.elastic_store_time'));
    $last30Minutes = Carbon::now()->subMinutes($minutes);

    if(config('app.is_full_fetch') == true){
        VehicleRecordArchived::chunk(500, function ($vehicles) use ($elasticsearch) {
                foreach ($vehicles as $vehicle) {
                    $elasticsearch->index([
                        'index' => 'vehicle_record_archiveds',
                        'id' => $vehicle->id,
                        'body' => $vehicle->toArray(),
                    ]);
                }
            });
    }else{
        VehicleRecordArchived::where('updated_at', '>=', $last30Minutes)
            ->chunk(500, function ($vehicles) use ($elasticsearch) {
                foreach ($vehicles as $vehicle) {
                    $elasticsearch->index([
                        'index' => 'vehicle_record_archiveds',
                        'id' => $vehicle->id,
                        'body' => $vehicle->toArray(),
                    ]);
                }
            });
    }
    $this->info('✅ Indexing vehicle_record_archiveds completed!');
}

}
