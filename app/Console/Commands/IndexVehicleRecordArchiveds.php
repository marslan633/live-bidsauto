<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleRecord;
use Illuminate\Support\Facades\App;


class IndexVehicleRecordArchiveds extends Command
{
    protected $signature = 'index:vehicle-record-archiveds';
    protected $description = 'Index all vehicle records to Elasticsearch';

    public function handle()
{
    $this->info('🚀 Indexing vehicle_record_archiveds started...');

    $elasticsearch = app('Elasticsearch');

    \App\Models\VehicleRecordArchived::chunk(500, function ($vehicles) use ($elasticsearch) {
        foreach ($vehicles as $vehicle) {
            $elasticsearch->index([
                'index' => 'vehicle_record_archiveds',
                'id' => $vehicle->id,
                'body' => $vehicle->toArray(),
            ]);
        }
    });

    $this->info('✅ Indexing vehicle_record_archiveds completed!');
}

}
