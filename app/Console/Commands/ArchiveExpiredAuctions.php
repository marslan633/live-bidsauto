<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveExpiredAuctionsJob;
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
    protected $signature = 'process:expired-auction-archive';

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
            $this->info("Process Archived Expired Auction Data started at: " . $startDateTime);
            Log::info("Process Archived Expired Auction Data started at: " . $startDateTime);

            $cronRun = DB::connection('mysql')->table('cron_run_history')->insertGetId([
                'cron_name' => 'process_auction_archive',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $batchSize = 100;
            // $expiredRecords = VehicleRecord::whereRaw("STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ') < ?", [now()])->get();
            $totalArchived = 0;
            DB::table('vehicle_records')
            ->whereRaw("STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ') < ?", [now()])
            ->orderBy('id', 'asc')
            ->limit(1000)
            ->chunk($batchSize, function ($expiredRecords) use (&$totalArchived) {
                foreach ($expiredRecords as $record) {
                    Log::info('Archived Expired Acution Job Running For: ' . $record->id);
                    ArchiveExpiredAuctionsJob::dispatch($record->id);
                }
                $totalArchived += count($expiredRecords);
            });

            if ($totalArchived === 0) {
                $this->info("No expired auctions found.");
                Log::info("No expired auctions found.");
                DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
                    'end_time' => Carbon::now(),
                    'status' => 'success',
                    'updated_at' => now(),
                ]);
                return;
            }

            $this->info("Successfully archived and deleted {$totalArchived} expired auctions.");
            Log::info("Successfully archived and deleted {$totalArchived} expired auctions.");

            DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
                'total_records' => $totalArchived,
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

        } catch (Exception $e) {
            $this->error("An error occurred while archiving expired auctions.");
            Log::error("Error in auction:archive cron job - " . $e->getMessage());

            DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            // Send email notification
            $cronJobName = 'process_auction_archive';
            // $adminEmails = explode(',', env('ADMIN_EMAIL'));
            // Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }
    }
}
