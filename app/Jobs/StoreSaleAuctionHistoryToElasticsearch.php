<?php
namespace App\Jobs;

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
        $bulkData = [];

        $this->histories->load([
            'domain',
            'status',
            'seller',
        ]);

        foreach ($this->histories as $history) {
            Log::info('Indexing Sale Auction History', ['history_id' => $history->id]);

            $historyData = $history->toArray();

            // Format sale_date as ISO8601 (for strict_date_optional_time)
            $historyData['sale_date'] = Carbon::parse($historyData['sale_date'])->toIso8601String();

            // Format created_at and updated_at to "Y-m-d H:i:s"
            $historyData['created_at'] = Carbon::parse($historyData['created_at'])->format('Y-m-d H:i:s');
            $historyData['updated_at'] = Carbon::parse($historyData['updated_at'])->format('Y-m-d H:i:s');

            $bulkData[] = [
                'index' => [
                    '_index' => 'sale_auction_histories',
                    '_id' => $history->id,
                ]
            ];

            $bulkData[] = $historyData;

            Log::info('Preparing to index sale auction history', ['history_id' => $history->id]);
        }

        if (!empty($bulkData)) {
            try {
                Log::info('Bulk Index Data: ', ['bulk_data' => json_encode($bulkData)]);

                $response = $client->bulk(['body' => $bulkData]);

                Log::info('Bulk Indexing Response: ', ['response' => $response]);

                if (isset($response['errors']) && $response['errors']) {
                    Log::error('Errors while indexing sale auction history', ['errors' => $response['items']]);
                } else {
                    Log::info('Sale Auction History records successfully indexed.');
                }
            } catch (\Exception $e) {
                Log::error('Error in Bulk Indexing: ', ['error' => $e->getMessage()]);
            }
        } else {
            Log::info('No sale auction history records found for indexing.');
        }
    }
}
