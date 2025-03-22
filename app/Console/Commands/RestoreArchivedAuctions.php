<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\VehicleRecord;
use App\Models\VehicleRecordArchived;
use App\Models\SaleAuctionHistory;
use App\Mail\CronJobFailedMail;

class RestoreArchivedAuctions extends Command
{
    protected $signature = 'auction:restore-archived';
    protected $description = 'Restore archived auction data back to VehicleRecord and clean up SaleAuctionHistory';

    public function handle()
    {
        try {
            $startDateTime = Carbon::now();
            $this->info("Process Restore Archived Auction Data started at: " . $startDateTime);
            Log::info("Process Restore Archived Auction Data started at: " . $startDateTime);

            $archivedRecords = VehicleRecordArchived::all();

            if ($archivedRecords->isEmpty()) {
                $this->info("No archived auctions found to restore.");
                Log::info("No archived auctions found to restore.");
                return;
            }

            foreach ($archivedRecords as $record) {
                // Restore the record back to VehicleRecord
                VehicleRecord::create($record->toArray());

                // Remove the record from SaleAuctionHistory
                SaleAuctionHistory::where('vin', $record->vin)->delete();

                // Delete the specific record from VehicleRecordArchived
                $record->delete();
            }

            $count = $archivedRecords->count();

            $this->info("Successfully restored {$count} archived auctions and cleaned up SaleAuctionHistory.");
            Log::info("Successfully restored {$count} archived auctions and cleaned up SaleAuctionHistory.");


        } catch (\Exception $e) {
            Log::error("Error in auction:restore-archived cron job - " . $e->getMessage());
            $this->error("An error occurred while restoring archived auctions.");

            // Send email notification
            $cronJobName = 'restore_auction_archive';
            $adminEmails = explode(',', env('ADMIN_EMAIL'));
            Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }
    }
}
