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
            $startTime = microtime(true);
            $startDateTime = Carbon::now();
            $this->info("Process Restore Archived Auction Data started at: " . $startDateTime);
            Log::info("Process Restore Archived Auction Data started at: " . $startDateTime);

            $cronRun = DB::table('cron_run_history')->insertGetId([
                'cron_name' => 'restore_auction_archive',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $archivedRecords = VehicleRecordArchived::all();

            if ($archivedRecords->isEmpty()) {
                $this->info("No archived auctions found to restore.");
                Log::info("No archived auctions found to restore.");
                DB::table('cron_run_history')->where('id', $cronRun)->update([
                    'end_time' => Carbon::now(),
                    'status' => 'success',
                    'updated_at' => now(),
                ]);
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

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'total_records' => $count,
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

        } catch (Exception $e) {
            Log::error("Error in auction:restore-archived cron job - " . $e->getMessage());
            $this->error("An error occurred while restoring archived auctions.");

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // Send email notification
            $cronJobName = 'restore_auction_archive';
            $adminEmails = explode(',', env('ADMIN_EMAIL'));
            Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }
    }
}