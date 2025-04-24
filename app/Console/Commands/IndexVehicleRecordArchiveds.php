<?php

namespace App\Console\Commands;

use App\Jobs\IndexVehicleRecordArchivedsJob;
use Illuminate\Console\Command;
use App\Models\VehicleRecord;
use App\Models\VehicleRecordArchived;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexVehicleRecordArchiveds extends Command
{
    protected $signature = 'index:vehicle-record-archiveds';
    protected $description = 'Index all vehicle records to Elasticsearch';

    public function handle()
    {
        $this->info('Dispatching IndexVehicleRecordsJob...');
        IndexVehicleRecordArchivedsJob::dispatch();
        $this->info('✅ Job dispatched!');
    }

}
