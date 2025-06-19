<?php

namespace App\Console\Commands\KvmThree;

use Illuminate\Console\Command;

use App\Jobs\KvmThree\ExpireAndStatusSaleJob;
use App\Models\VehicleRecord;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;

class ExpireAndStatusSale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:expire-and-status-sale-with-elasticsearch';

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


            $response = $client->search([
                'index' => 'cron_run_histories',
                'body' => [
                    'size' => 1,
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['cron_name' => 'process_expire_status_sale']],
                                ['term' => ['status' => 'success']]
                            ]
                        ]
                    ],
                    'sort' => [
                        ['start_time' => ['order' => 'desc']]
                    ]
                ]
            ]);

            $hits = $response['hits']['hits'];

            $minutes = 45;

            if (!empty($hits)) {
                $lastCron = $hits[0]['_source'];
                if (!empty($lastCron['end_time'])) {
                    $endTime = Carbon::parse($lastCron['end_time']);
                    $timeDifference = max(0, $endTime->diffInMinutes(now()));
                    Log::info('Time Difference Active '. $timeDifference);
                    if ($timeDifference > 45) {
                        $minutes = $timeDifference + 10;
                    } elseif ($timeDifference === 45) {
                        $minutes = $timeDifference + 5;
                    }
                }
            }
            Log::info('Minutes Active '. $minutes);

            $cronRun = '';
            $startDateTime = Carbon::now();
            $this->info("Process Archived Expired Auction Data started at: " . $startDateTime);
            Log::info("Process Archived Expired Auction Data started at: " . $startDateTime);

            $params = [
                'index' => 'cron_run_histories',
                'body'  => [
                    'cron_name'   => 'process_expire_status_sale',
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
                        'command_name' => 'process:exipre-and-status-sale-with-elasticsearch',
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
            $minutes = Carbon::now()->subMinutes($minutes);
            $isFullFetch = config('app.is_full_fetch', false);

            $totalArchived = 0;
            $query = VehicleRecord::query();
            $query->where('data_source', 1)->where('status_id', 3)->whereRaw(
                "DATE_FORMAT(STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ'), '%Y-%m-%d %H:%i') < ?",
                [now()->format('Y-m-d H:i')]
            )->limit(10);
            if (!$isFullFetch) {
                $query->where('updated_at', '>=', $minutes);
            }
            $query->chunk(100, function ($expiredRecords) use (&$totalArchived) {
                ExpireAndStatusSaleJob::dispatch($expiredRecords->toArray());
                $totalArchived += count($expiredRecords);
            });
            Log::info("ExipreAndStatusSale {$totalArchived} records.");

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
                            'command_name' => 'process:exipre-and-status-sale-with-elasticsearch',
                            'error' => 'ERROR: PROCESS CACHED DATA TO DATABASE UPDATED',
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                }

                return;
            }

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
                        'command_name' => 'process:exipre-and-status-sale-with-elasticsearch',
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
                    'command_name' => 'process:exipre-and-status-sale-with-elasticsearch',
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
            $cronJobName = 'process_expire_status_sale';
            // $adminEmails = explode(',', env('ADMIN_EMAIL'));
            // Mail::to($adminEmails)->send(new CronJobFailedMail($e->getMessage(), $cronJobName));
        }
    }
}
