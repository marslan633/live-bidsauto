<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{VehicleRecord, VehicleRecordArchived, Status};

class ProcessArchivedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:archived-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move archived data from VehicleRecord to VehicleRecordArchived based on status_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $soldStatus = Status::where('name', 'sold')->pluck('id')->first();

        // Fetch records with status sold from VehicleRecord
        $records = VehicleRecord::where('status_id', $soldStatus)->get();

        if ($records->isEmpty()) {
            $this->info('No records found to archive.');
            return;
        }

        // Insert the records into VehicleRecordArchived
        foreach ($records as $record) {
            VehicleRecordArchived::create($record->toArray());
        }

        // Delete the records from VehicleRecord
        VehicleRecord::where('status_id', $soldStatus)->delete();

        // Output the result
        $this->info('Archived and deleted ' . $records->count() . ' records.');
    }
}