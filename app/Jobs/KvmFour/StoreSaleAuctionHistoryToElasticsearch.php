<?php
namespace App\Jobs\KvmFour;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StoreSaleAuctionHistoryToElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $histories;

    public function __construct($histories)
    {
        $this->queue = 'store_sale_auction_history_records_to_elasticsearch_job';
        $this->histories = $histories;
    }

    public function handle()
    {
        $client = app('ElasticsearchKvmFour');
        $clientKvmOne = app('ElasticsearchKvmOne');
        $this->histories->load([
            'domain',
            'status',
            'seller',
        ]);

        foreach ($this->histories as $history) {
            Log::info('Indexing Sale Auction History', ['history_id' => $history->id]);

            $historyData = $history->toArray();

            // Format dates
            $historyData['vin'] = strtolower($historyData['vin']);
            $historyData['sale_date'] = Carbon::parse($historyData['sale_date'])->toIso8601String();
            $historyData['created_at'] = Carbon::parse($historyData['created_at'])->format('Y-m-d H:i:s');
            $historyData['updated_at'] = Carbon::parse($historyData['updated_at'])->format('Y-m-d H:i:s');

            try {
                // Upsert parameters
                $params = [
                    'index' => 'sale_auction_histories',
                    'id'    => $history->id,
                    'body'  => [
                        'script' => [
                            'source' => 'ctx._source.putAll(params.historyData)',
                            'params' => [
                                'historyData' => $historyData
                            ],
                        ],
                        'upsert' => $historyData,
                    ]
                ];

                $response = $client->update($params);

                Log::info('Upserted Sale Auction History in Elasticsearch', [
                    'history_id' => $history->id,
                    'response' => $response,
                ]);
            } catch (\Exception $e) {
                $clientKvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.4',
                        'error_type' => 'Internal Server Error',
                        'command_name' => 'store_sale_auction_history_records_to_elasticsearch_job',
                        'error' => 'Error Upserting Sale Auction History in Elasticsearch ' . json_encode($e->getMessage()),
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }
        }
    }
}
