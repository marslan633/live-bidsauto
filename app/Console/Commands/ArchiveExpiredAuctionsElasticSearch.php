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

class ArchiveExpiredAuctionsElasticSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:expired-auction-archive-with-elasticsearch';

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
        $client = app('ElasticsearchKvmOne');

        try {
            $url = config('app.cron_history_api_url') . '/cron-run-histories';

            $cronRun = '';
            $startDateTime = Carbon::now();
            $this->info("Process Archived Expired Auction Data started at: " . $startDateTime);
            Log::info("Process Archived Expired Auction Data started at: " . $startDateTime);

            $params = [
                'index' => 'cron_run_histories',
                'body'  => [
                    'cron_name'   => 'process_auction_archive',
                    'start_time'  => $startDateTime->toIso8601String(),
                    'status'      => 'running',
                    'created_at'  => now()->toIso8601String(),
                    'updated_at'  => now()->toIso8601String(),
                ]
            ];
            $response = $client->index($params);

            if ($response['_id']) {
                Log::info('PROCESS AUCTION ARCHIVED DATA TO DATABASE CREATED');
                // Handle the successful API cronRunResponse
                $cronRun = $response['_id'];
                // Optionally, you can update the cron record with the API response or status
            } else {
                $client->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'process:expired-auction-archive-with-elasticsearch',
                        'error' => 'Error: PROCESS AUCTION ARCHIVED DATA TO DATABASE CREATED',
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }
            $updateUrl = $url . "/$cronRun";
            $batchSize = intval(config('app.batch_size'));
            // $expiredRecords = VehicleRecord::whereRaw("STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ') < ?", [now()])->get();


            // sale_date => 16-05-2025
            // expire_date = 17-05-2025

            $totalArchived = 0;
            DB::table('vehicle_records')
            ->whereRaw(
                "DATE_FORMAT(DATE_ADD(STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ'), INTERVAL 28 HOUR), '%Y-%m-%d %H:%i') <= ?",
                [now()->format('Y-m-d H:i')]
            )
            ->orderBy('created_at') // Required for Laravel 11 chunking
            ->chunk($batchSize, function ($expiredRecords) use (&$totalArchived) {
                foreach ($expiredRecords as $record) {
                    Log::info('Expired Archived Record', ['record', json_encode($record)]);
                    ArchiveExpiredAuctionsJob::dispatch($record->id);
                }
                $totalArchived += count($expiredRecords);
            });

            if ($totalArchived === 0) {
                $this->info("No expired auctions found.");
                Log::info("No expired auctions found.");


                if($cronRun){

                    $client->update([
                        'index' => 'cron_run_histories',
                        'id'    => $cronRun, // previously captured _id
                        'body'  => [
                            'doc' => [
                                'end_time'    => now()->toIso8601String(),
                                'status'      => 'success',
                                'updated_at'  => now()->toIso8601String(),
                            ]
                        ]
                    ]);

                    Log::info('PROCESS CACHED DATA TO DATABASE UPDATED');
                } else {

                    $client->index([
                        'index' => 'error_logs',
                        'body' => [
                            'server_name' => 'KVM4.3',
                            'error_type' => 'Internal Server Error',
                            'command_name' => 'process:expired-auction-archive-with-elasticsearch',
                            'error' => 'ERROR: PROCESS CACHED DATA TO DATABASE UPDATED',
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                }

                return;
            }

            $this->info("Successfully archived and deleted {$totalArchived} expired auctions.");
            Log::info("Successfully archived and deleted {$totalArchived} expired auctions.");

            if($cronRun){

                $client->update([
                    'index' => 'cron_run_histories',
                    'id'    => $cronRun, // previously captured _id
                    'body'  => [
                        'doc' => [
                            'end_time'    => now()->toIso8601String(),
                            'status'      => 'success',
                            'updated_at'  => now()->toIso8601String(),
                        ]
                    ]
                ]);

                Log::info('PROCESS CACHED DATA TO DATABASE UPDATED');
            } else {

                $client->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'process:expired-auction-archive-with-elasticsearch',
                        'error' => 'ERROR: PROCESS CACHED DATA TO DATABASE UPDATED',
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }

        } catch (Exception $e) {
            // Remote Connection to KVM4.1
            $client = app('ElasticsearchKvmOne');

            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process:expired-auction-archive-with-elasticsearch',
                    'error' => 'Error in auction:archive cron job - ' . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);


            $client->update([
                'index' => 'cron_run_histories',
                'id'    => $cronRun, // previously captured _id
                'body'  => [
                    'doc' => [
                        'end_time'    => now()->toIso8601String(),
                        'status'      => 'failed',
                        'error_message' => $e->getMessage(),
                        'updated_at'  => now()->toIso8601String(),
                    ]
                ]
            ]);

            // Send email notification
            $cronJobName = 'process_auction_archive';
            // $adminEmails = explode(',', env('ADMIN_EMAIL'));
            // Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }
    }
}
