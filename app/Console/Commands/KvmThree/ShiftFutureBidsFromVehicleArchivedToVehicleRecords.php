<?php

namespace App\Console\Commands\KvmThree;

use App\Jobs\KvmThree\ShiftFutureBidsFromVehicleArchivedToVehicleRecordsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShiftFutureBidsFromVehicleArchivedToVehicleRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:shift-future-bids-from-vehicle-archived-to-vehicle-records';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::table('vehicle_record_archiveds')
        ->whereRaw(
            "DATE_FORMAT(STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ'), '%Y-%m-%d %H:%i') >= ?",
            [now()->format('Y-m-d H:i')]
        )
        ->orderBy('id')  // important to order by a unique column for chunking consistency
        ->chunk(500, function ($records) {
            ShiftFutureBidsFromVehicleArchivedToVehicleRecordsJob::dispatch($records->toArray());
        });

    }
}
