<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;

class StoreSaleAuctionHistoryToElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $histories;

    /**
     * The name of the job.
     *
     * @var string
     */

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($histories)
    {
        $this->queue = 'store_sale_auction_history_records_to_elasticsearch_job';
        $this->histories = $histories;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = app('ElasticsearchKvmFour');

        $bulkData = [];
        foreach ($this->histories as $history) {
            $bulkData[] = ['index' => ['_index' => 'sale_auction_histories', '_id' => $history->id]];
            $bulkData[] = $history->toArray();
        }

        if (!empty($bulkData)) {
            $client->bulk(['body' => $bulkData]);
            Log::info('Sale Auction History records indexed in Elasticsearch by job.');
        }
    }
}
