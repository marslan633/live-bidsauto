<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleRecord;
use App\Models\VehicleRecordArchived;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;


class IndexVehicleRecordArchiveds extends Command
{
    protected $signature = 'index:vehicle-record-archiveds';
    protected $description = 'Index all vehicle records to Elasticsearch';

    public function handle()
{
    $this->info('🚀 Indexing vehicle_record_archiveds started...');

    $elasticsearch = app('Elasticsearch');
    $last30Minutes = Carbon::now()->subMinutes(30);

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

    $this->info('✅ Indexing vehicle_record_archiveds completed!');
}

}
