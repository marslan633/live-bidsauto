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

class ExpireAndStatusSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $records;

    /**
     * Create a new job instance.
     */
    public function __construct($records)
    {
        $this->queue = 'expire_and_status_sale_queue';
        $this->records = $records;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info('Exipre And Status Sale Job Handle Function Calling');
        $client = app('ElasticsearchKvmOne');

        $updatedVehicleRecordsData = [];
        $updatedSaleData = [];
        $newSaleData = [];
        try {
            foreach($this->records as $record){
                $record = (array) $record;
                $now = Carbon::now();

                $record['status_id'] = 7;
                $record['data_source'] = 2;
                $updatedVehicleRecordsData[] = [
                    'id' => $record['id'],
                    'status_id' => 7,
                    'data_source' => 2,
                    'updated_at' => $now
                ];
                Log::info('Expired and Status Sale Record Updated', ['record' => json_encode([
                    'id' => $record['id'],
                    'status_id' => 7,
                    'data_source' => 2,
                    'updated_at' => $now,
                    'lot_id' => $record['lot_id'],
                    'sale_date' => $record['sale_date']
                ])]);


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
                    $updatedSaleData[] = array_merge(['id' => $saleAuctionRecord->id], $saleData);
                    Log::info('ExpireAndStatusSaleJob Sale Auctio History Record Updated', ['record' => json_encode(array_merge(['id' => $saleAuctionRecord->id], $saleData))]);
                }else{
                    $newSaleData[] = $saleData;
                    Log::info('ExpireAndStatusSaleJob Sale Auctio History Record Created', ['record' => json_encode($saleData)]);
                }
            }
            DB::table('vehicle_records')->upsert($updatedVehicleRecordsData,['id']);
            DB::table('sale_auction_histories')->upsert($updatedSaleData,['id']);
            DB::table('sale_auction_histories')->insert($newSaleData);
        } catch (\Exception $e) {
            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'exipre_and_status_sale_queue',
                    'error' => "Error processing auction record VIN: {$record['vin']} - ". json_encode($e->getMessage()) ,
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }
}
