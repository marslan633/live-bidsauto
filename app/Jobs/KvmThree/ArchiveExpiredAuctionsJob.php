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

        $updatedVehicleRecordsData = [];
        $updatedSaleData = [];
        $newSaleData = [];
        foreach($this->records as $record){
                $record = (array) $record;
                try {
                $now = Carbon::now();
                $saleDate = Carbon::parse($record['sale_date']);
                if($saleDate->gt($now) && $record['status_id'] == 3){
                    $updatedVehicleRecordsData[] = [
                        'id' => $record['id'],
                        'data_source' => 1,
                        'updated_at' => $now
                    ];
                    Log::info('Active and Status Sale Record Updadted', ['record' => json_encode([
                        'id' => $record['id'],
                        'data_source' => 2,
                        'updated_at' => $now,
                        'lot_id' => $record['lot_id'],
                        'sale_date' => $record['sale_date']
                    ])]);
                }


                DB::table('vehicle_records')->upsert($updatedVehicleRecordsData,['id']);
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
