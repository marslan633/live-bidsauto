<?php

namespace App\Jobs\KvmThree;

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

    protected $records;

    /**
     * Create a new job instance.
     */
    public function __construct($records)
    {
        $this->queue = 'expired_auction_archive_queue';
        $this->records = $records;
        // Log::info('Archived Expired Job Construter Calling');
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info('Archived Expired Job Handle Function Calling');
        $client = app('ElasticsearchKvmOne');
        foreach($this->records as $record){
                $record = (array) $record;
                try {
                $now = Carbon::now();
                $saleDate = Carbon::parse($record['sale_date']);
                if($saleDate < now() && $record['status_id'] == 3){
                    $record['status_id'] = 7;
                    $record['data_source'] = 2;
                    // DB::table('vehicle_records')->where('id', $record['id'])->update([
                    //     'status_id' => 7,
                    //     'data_source' => 2,
                    //     'updated_at' => $now
                    // ]);
                    Log::info('Expired and Status Sale Record Updadted', ['record' => json_encode([
                        'id' => $record['id'],
                        'status_id' => 7,
                        'data_source' => 2,
                        'updated_at' => $now
                    ])]);
                }elseif($saleDate > now() && $record['status_id'] != 3){
                    // DB::table('vehicle_records')->where('id', $record['id'])->update([
                    //     'data_source' => 2,
                    //     'updated_at' => $now
                    // ]);
                    Log::info('Active and Status Not Sale Record Updadted', ['record' => json_encode([
                        'id' => $record['id'],
                        'data_source' => 2,
                        'updated_at' => $now
                    ])]);
                }

                $record['updated_at'] = $now;


                $saleAuctionRecord = DB::table('sale_auction_histories')
                ->where([
                    'vin' => $record['vin'],
                    'lot_id' => $record['lot_id'],
                    'sale_date' => $record['sale_date'],
                ])->first();

                $saleData = [
                    'vin' => $record['vin'],
                    'domain_id' => $record['domain_id'],
                    'sale_date' => $record['sale_date'],
                    'lot_id' => $record['lot_id'],
                    'bid' => $record['bid'],
                    'odometer_mi' => $record['odometer_mi'],
                    'status_id' => $record['status_id'],
                    'seller_id' => $record['seller_id'],
                    'created_at' => $now,
                    'updated_at' => $now
                ];
                if($saleAuctionRecord){
                    unset($saleData['created_at']);
                    // DB::connection('mysql')
                    //     ->table('sale_auction_histories')
                    //     ->where('id', $saleAuctionRecord->id)
                    //     ->update($saleData);
                    Log::info('Sale Auctio History Record Updadted', ['record' => json_encode($saleAuctionRecord)]);
                }else{
                    // DB::table('sale_auction_histories')->insert($saleData);
                    Log::info('Sale Auctio History Record Created', ['record' => json_encode($saleData)]);
                }
            } catch (\Exception $e) {
                $client->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'expired_auction_archive_queue',
                        'error' => "Error processing auction record VIN: {$record['vin']} - ". json_encode($e->getMessage()) ,
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }
            }
    }
}
