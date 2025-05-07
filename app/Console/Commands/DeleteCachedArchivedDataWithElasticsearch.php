<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elasticsearch\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeleteCachedArchivedDataWithElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:delete-cached-archived-data-with-elasticsearch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete records with status "completed" from Elasticsearch index "vehicle_archived_api_data" older than 30 minutes to 1 hour ago';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Starting to delete completed status records older than 30 minutes to 1 hour from vehicle_archived_api_data...');

        // Elasticsearch client
        $client = app('ElasticsearchKvmOne');

        // Time range: 1 hour ago to 30 minutes ago
        $now = Carbon::now()->utc(); // Ensure you're using UTC to match Elasticsearch
        $startTime = $now->subMinutes(60)->toDateTimeString(); // 1 hour ago
        $endTime = $now->subMinutes(30)->toDateTimeString();   // 30 minutes ago

        // Log the start time and current time for debugging purposes
        Log::info('Deleting records with status "completed" between', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'now' => $now->toDateTimeString(),
        ]);

        try {
            // Search for documents with status "completed" and within the time range
            $params = [
                'index' => 'vehicle_archived_api_data',
                'scroll' => '1m', // Set scroll time context
                'size' => 200, // Fetch 200 records at a time
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'match' => [
                                        'status' => 'completed'  // Filter by completed status
                                    ]
                                ]
                            ],
                            'filter' => [
                                [
                                    'range' => [
                                        'updated_at' => [
                                            'gte' => $startTime, // 1 hour ago
                                            'lte' => $endTime    // 30 minutes ago
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // Perform search query
            $response = $client->search($params);
            Log::info('Elasticsearch response', ['response' => json_encode($response)]);

            // Get the scroll ID from the response
            $scrollId = $response['_scroll_id'] ?? null;
            if (!$scrollId) {
                $this->error('Scroll ID is missing in the response.');
                return;
            }

            // Continue scrolling and deleting until no more results are returned
            $totalDeleted = 0;
            do {
                $hits = $response['hits']['hits'];

                if (count($hits) == 0) {
                    break;
                }

                // Prepare delete operations
                $deleteParams = [];
                foreach ($hits as $hit) {
                    $deleteParams[] = [
                        'delete' => [
                            '_index' => 'vehicle_archived_api_data',
                            '_id' => $hit['_id']
                        ]
                    ];
                }

                // Perform bulk delete
                if (!empty($deleteParams)) {
                    $bulkResponse = $client->bulk(['body' => $deleteParams]);

                    if (isset($bulkResponse['errors']) && $bulkResponse['errors']) {
                        Log::error('Bulk delete errors', ['response' => json_encode($bulkResponse)]);
                    } else {
                        $deletedCount = count($deleteParams);
                        $totalDeleted += $deletedCount;
                        $this->info("Successfully deleted $deletedCount records.");
                    }
                }

                // Fetch next batch of results using scroll
                $response = $client->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => '1m'
                ]);

            } while (count($hits) > 0);

            $this->info("Completed deleting $totalDeleted records.");

        } catch (\Exception $e) {
            Log::error('Error deleting records: ' . $e->getMessage());
            $this->error('Error deleting records: ' . $e->getMessage());
        }
    }
}
