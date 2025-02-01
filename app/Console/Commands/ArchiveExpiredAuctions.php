<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleRecord;
use App\Models\VehicleRecordArchived;
use App\Models\SaleAuctionHistory;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ArchiveExpiredAuctions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auction:archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move expired auctions from VehicleRecord to VehicleRecordArchived';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {

            $startTime = microtime(true);
            $startDateTime = Carbon::now();
            $this->info("Process Archived Data started at: " . $startDateTime);
            \Log::info("Process Archived Data started at: " . $startDateTime);

            $cronRun = DB::table('cron_run_history')->insertGetId([
                'cron_name' => 'process_auction_archive',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        
            $expiredRecords = VehicleRecord::where('sale_date', '<', now())->get();

            if ($expiredRecords->isEmpty()) {
                $this->info("No expired auctions found.");
                Log::info("No expired auctions found.");
                DB::table('cron_run_history')->where('id', $cronRun)->update([
                    'end_time' => Carbon::now(),
                    'status' => 'success',
                    'updated_at' => now(),
                ]);
                return;
            }

            foreach ($expiredRecords as $record) {
                VehicleRecordArchived::create($record->toArray());

                // Insert record into SaleAuctionHistory
                SaleAuctionHistory::create([
                    'vin' => $record->vin,
                    'domain_id' => $record->domain_id,
                    'sale_date' => $record->sale_date,
                    'lot_id' => $record->lot_id,
                    'bid' => $record->bid,
                    'odometer_mi' => $record->odometer_mi,
                    'status_id' => $record->status_id,
                    'seller_id' => $record->seller_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $record->delete();
            }

            $count = $expiredRecords->count();
            $this->info("Successfully archived and deleted {$count} expired auctions.");
            Log::info("Successfully archived and deleted {$count} expired auctions.");

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'total_records' => $count,
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

        } catch (Exception $e) {
            Log::error("Error in auction:archive cron job - " . $e->getMessage());
            $this->error("An error occurred while archiving expired auctions.");

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // Send email notification
            $adminEmail = env('ADMIN_EMAIL');
            Mail::to($adminEmail)->send(new CronJobFailedMail($e->getMessage()));
        }
    }
}