<?php

namespace App\Console\Commands;

use App\Jobs\IndexVehicleRecordsJob;
use Illuminate\Console\Command;


class IndexVehicleRecords extends Command
{
    protected $signature = 'index:vehicle-records';
    protected $description = 'Index all vehicle records to Elasticsearch';

    public function handle()
    {
        $this->info('Dispatching IndexVehicleRecordsJob...');
        IndexVehicleRecordsJob::dispatch();
        $this->info('✅ Job dispatched!');
    }

}
