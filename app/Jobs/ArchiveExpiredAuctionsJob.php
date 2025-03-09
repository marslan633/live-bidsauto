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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveExpiredAuctionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $recordId;

    /**
     * Create a new job instance.
     */
    public function __construct($recordId)
    {
        $this->recordId = $recordId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {

            $record  = DB::connection('mysql')->table('vehicle_records')->where('id', $this->recordId)->first();

            if (!$record) {
                Log::error("Auction record not found for ID: {$this->recordId}");
                return;
            }

            $record = (array) $record;
            $record['status_id'] = 7;
              // Check if the record already exists in VehicleRecordArchived
              $archivedRecord = DB::connection('mysql')->table('vehicle_record_archiveds')->where('vin', $record['vin'])->first();

              if ($archivedRecord) {
                  // If it exists, update the existing record
                  $archivedRecord->update($record);
              } else {
                  // If it doesn't exist, create a new one
                  DB::connection('mysql')->table('vehicle_record_archiveds')->insert($record);
              }

              // Insert record into SaleAuctionHistory
              DB::connection('mysql')->table('sale_auction_histories')->insert($record);

              DB::table('vehicle_records')->where('id', $this->recordId)->delete();

        } catch (\Exception $e) {
            Log::error("Error processing auction record VIN: {$record['vin']} - " . $e->getMessage());
        }
    }
}
