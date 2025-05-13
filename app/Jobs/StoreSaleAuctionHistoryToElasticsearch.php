<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;
use App\Models\SaleAuctionHistory; // Assuming this is the model for sale_auction_histories

class StoreSaleAuctionHistoryToElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $histories;

    /**
     * Create a new job instance.
     *
     * @param $histories
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

        // Eager load relationships inside the job
        $this->histories->load([
            'domain',  // Load the related domain
            'status',  // Load the related status
            'seller',  // Load the related seller
        ]);

        foreach ($this->histories as $history) {
            // Prepare the bulk data for Elasticsearch
            Log::info('Indexing Sale Auction History', ['history_id' => $history->id]);

            // Convert the history data to an array, including relationships
            $historyData = $history->toArray();

            // Add the history data to the bulk request for Elasticsearch
            $bulkData[] = [
                'index' => [
                    '_index' => 'sale_auction_histories',
                    '_id' => $history->id,
                ]
            ];

            // Adding the history data to the bulk request body
            $bulkData[] = $historyData;

            Log::info('Preparing to index sale auction history', ['history_id' => $history->id]);
        }

        // If we have data to index
        if (!empty($bulkData)) {
            try {
                // Debugging: Log the bulk data structure before sending it
                Log::info('Bulk Index Data: ', ['bulk_data' => json_encode($bulkData)]);

                // Execute the bulk request to Elasticsearch
                $response = $client->bulk(['body' => $bulkData]);

                // Debugging: Log the response from Elasticsearch
                Log::info('Bulk Indexing Response: ', ['response' => $response]);

                // Check if Elasticsearch returned any errors
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
