<?php

namespace App\Jobs;

use App\Models\VehicleRecord;
use App\Models\VehicleRecordArchived;
use App\Models\SaleAuctionHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ArchiveExpiredAuctionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $record;

    /**
     * Create a new job instance.
     */
    public function __construct(VehicleRecord $record)
    {
        $this->record = $record;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $record = $this->record;

              // Check if the record already exists in VehicleRecordArchived
              $archivedRecord = VehicleRecordArchived::where('vin', $record->vin)->first();

              if ($archivedRecord) {
                  // If it exists, update the existing record
                  $archivedRecord->update($record->toArray());
              } else {
                  // If it doesn't exist, create a new one
                  VehicleRecordArchived::create($record->toArray());
              }

              // Insert record into SaleAuctionHistory
              SaleAuctionHistory::create($record);

              $record->delete();

        } catch (\Exception $e) {
            Log::error("Error processing auction record VIN: {$this->record->vin} - " . $e->getMessage());
        }
    }
}
