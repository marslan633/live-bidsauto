<?php

namespace App\Jobs;

use App\Models\VehicleRecord;
use App\Models\VehicleRecordArchived;
use App\Models\SaleAuctionHistory;
use Carbon\Carbon;
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
        $this->queue = 'expired_auction_archive_queue';
        $this->recordId = $recordId;
        // Log::info('Archived Expired Job Construter Calling');
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info('Archived Expired Job Handle Function Calling');
        try {

            $record  = DB::connection('mysql')->table('vehicle_records')->where('id', $this->recordId)->first();
            // Log::info('Record Fetched', ['record' => json_encode($record)]);

            if (!$record) {
                Log::info("Auction record not found for ID: {$this->recordId}");
                return;
            }

            $record = (array) $record;
            $record['status_id'] = 7;
            $record['updated_at'] = Carbon::now();

              // Check if the record already exists in VehicleRecordArchived
              $archivedRecord = DB::connection('mysql')->table('vehicle_record_archiveds')->where('vin', $record['vin'])->first();

              if ($archivedRecord) {
                  // If it exists, update the existing record
                    // Log::info("Archived Expired Updating Record: ", ['record' => json_encode($archivedRecord)]);
                    DB::connection('mysql')
                    ->table('vehicle_record_archiveds')
                    ->where('id', $archivedRecord->id)
                    ->update($record);
              } else {
                  // If it doesn't exist, create a new one
                //   Log::info("Archived Expired Creating Record: ", ['record' => json_encode($record)]);
                $record['created_at'] = Carbon::now();
                DB::connection('mysql')->table('vehicle_record_archiveds')->insert($record);
              }

              // Insert record into SaleAuctionHistory
            //   Log::info("Archived Expired Record Insert In Sale_acution_histories: ", ['record' => json_encode([
            //     'vin' => $record['vin'],
            //     'domain_id' => $record['domain_id'],
            //     'sale_date' => $record['sale_date'],
            //     'lot_id' => $record['lot_id'],
            //     'bid' => $record['bid'],
            //     'odometer_mi' => $record['odometer_mi'],
            //     'status_id' => $record['status_id'],
            //     'seller_id' => $record['seller_id']
            //   ])]);

              DB::connection('mysql')->table('sale_auction_histories')->insert([
                'vin' => $record['vin'],
                'domain_id' => $record['domain_id'],
                'sale_date' => $record['sale_date'],
                'lot_id' => $record['lot_id'],
                'bid' => $record['bid'],
                'odometer_mi' => $record['odometer_mi'],
                'status_id' => $record['status_id'],
                'seller_id' => $record['seller_id'],
                'created_at' => $record['created_at'],
                'updated_at' => $record['updated_at']
              ]);


              DB::where('id', $this->recordId)->delete();
            //   Log::info('Vehicle Record Deleted ' . $this->recordId);
        } catch (\Exception $e) {
            Log::error("Error processing auction record VIN: {$record['vin']} - " . $e->getMessage());
        }
    }
}
