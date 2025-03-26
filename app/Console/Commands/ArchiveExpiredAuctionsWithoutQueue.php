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
use Illuminate\Support\Facades\Http;

class ArchiveExpiredAuctionsWithoutQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:expired-auction-archive-without-queue';

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
            $url = config('app.cron_history_api_url') . '/cron-run-histories';

            $startTime = microtime(true);
            $cronRun = '';
            $startDateTime = Carbon::now();
            $this->info("Process Archived Expired Auction Data started at: " . $startDateTime);
            Log::info("Process Archived Expired Auction Data started at: " . $startDateTime);

            // Remote Connection to KVM4.1
            $cronRunResponse = Http::timeout(120)->retry(3, 1000)->post($url, [
                'cron_name' => 'process_auction_archive',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($cronRunResponse->successful()) {
                Log::info('PROCESS AUCTION ARCHIVED DATA TO DATABASE CREATED');
                // Handle the successful API cronRunResponse
                $cronRun = $cronRunResponse->json()['id'] ?? null; // You can process the data as needed
                // Optionally, you can update the cron record with the API response or status
            } else {
                Log::info('Error: PROCESS AUCTION ARCHIVED DATA TO DATABASE CREATED');
            }

            $updateUrl = $url . "/$cronRun";
            $batchSize = intval(config('app.batch_size'));
            // $expiredRecords = VehicleRecord::whereRaw("STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ') < ?", [now()])->get();
            $totalArchived = 0;
            DB::table('vehicle_records')
            ->whereRaw("DATE_FORMAT(STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ'), '%Y-%m-%d %H:%i') <= ?", [now()->format('Y-m-d H:i')])
            ->orderBy('created_at') // Required for Laravel 11 chunking
            ->chunk($batchSize, function ($expiredRecords) use (&$totalArchived) {
                foreach ($expiredRecords as $record) {
                    try {
                        if (!$record) {
                            Log::info("Auction record not found for ID: {$record->id}");
                            return;
                        }

                        $record = (array) $record;
                        if($record['status_id'] == 3){
                            $record['status_id'] = 7;
                        }
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


                          DB::connection('mysql')->table('vehicle_records')->where('id', $record['id'])->delete();
                        //   Log::info('Vehicle Record Deleted ' . $record->id);
                    } catch (\Exception $e) {
                        Log::error("Error processing auction record VIN: {$record['vin']} - " . $e->getMessage());
                    }
                }
                $totalArchived += count($expiredRecords);
            });

            if ($totalArchived === 0) {
                $this->info("No expired auctions found.");
                Log::info("No expired auctions found.");


                // Remote Connection to KVM4.1
                $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->put($updateUrl, [
                    'end_time' => Carbon::now(),
                    'status' => 'success',
                    'updated_at' => now(),
                ]);

                if ($cronRunUpdateResponse->successful()) {
                    Log::info('PROCESS CACHED DATA TO DATABASE UPDATED');
                } else {
                    Log::info('ERROR: PROCESS CACHED DATA TO DATABASE UPDATED');
                }

                return;
            }

            $this->info("Successfully archived and deleted {$totalArchived} expired auctions.");
            Log::info("Successfully archived and deleted {$totalArchived} expired auctions.");

            if($cronRun){

                $updateUrl = $url . "/$cronRun";
                 // Remote Connection to KVM4.1
                 $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->put($updateUrl, [
                    'total_records' => $totalArchived,
                    'end_time' => Carbon::now(),
                    'status' => 'success',
                    'updated_at' => now(),
                ]);

                if ($cronRunUpdateResponse->successful()) {
                    Log::info('PROCESS CACHED DATA TO DATABASE UPDATED');
                } else {
                    Log::info('ERROR: PROCESS CACHED DATA TO DATABASE UPDATED');


                }

            }

        } catch (Exception $e) {
            $this->error("An error occurred while archiving expired auctions.");
            Log::error("Error in auction:archive cron job - " . $e->getMessage());

            // Remote Connection to KVM4.1
            $cronRunUpdateResponse = Http::timeout(120)->retry(3, 1000)->put($updateUrl, [
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            if ($cronRunUpdateResponse->successful()) {
                Log::info('PROCESS AUCTOIN ARCHIVED DATA TO DATABASE UPDATED');
            } else {
                Log::info('ERROR: PROCESS AUCTOIN ARCHIVED DATA TO DATABASE UPDATED');
            }

            // Send email notification
            $cronJobName = 'process_auction_archive';
            // $adminEmails = explode(',', env('ADMIN_EMAIL'));
            // Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }
    }
}
