<?php

namespace App\Console\Commands\KvmOne;

use Illuminate\Console\Command;
use Elasticsearch\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeleteCachedDataWithElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:delete-cached-data-with-elasticsearch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete records with status "completed" and older than 30 minutes from Elasticsearch index "vehicle_process_cached_api_data"';

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
        $this->info('Starting to delete records older than 30 minutes and with status "completed" from vehicle_process_cached_api_data...');

        // Elasticsearch client
        $client = app('ElasticsearchKvmOne');

        // Time range: 30 minutes ago to now
        $now = Carbon::now()->utc(); // Ensure you're using UTC to match Elasticsearch
        $startTime = $now->subMinutes(30)->toDateTimeString(); // 30 minutes ago

        // Log the start time and current time for debugging purposes
        Log::info('Deleting records older than 30 minutes with status "completed"', [
            'start_time' => $startTime,
            'now' => $now->toDateTimeString(),
        ]);

        try {
            // Search for documents older than 30 minutes and with status "completed"
            $params = [
                'index' => 'vehicle_process_cached_api_data',
                'scroll' => '1m', // Set scroll time context
                'size' => 300, // Fetch 200 records at a time
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
                                            'lte' => $startTime // Delete records older than 30 minutes
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
                            '_index' => 'vehicle_process_cached_api_data',
                            '_id' => $hit['_id']
                        ]
                    ];
                }

                // Perform bulk delete
                if (!empty($deleteParams)) {
                    $bulkResponse = $client->bulk(['body' => $deleteParams]);

                    if (isset($bulkResponse['errors']) && $bulkResponse['errors']) {
                        $client->index([
                            'index' => 'error_logs',
                            'body' => [
                                'server_name' => 'KVM4.1',
                                'error_type' => 'Internal Server Error',
                                'command_name' => 'process:delete-cached-data-with-elasticsearch',
                                'error' => 'Bulk delete error: ' . json_encode($bulkResponse),
                                'created_at' => now()->toIso8601String(),
                                'updated_at' => now()->toIso8601String(),
                            ],
                        ]);
                    } else {
                        $this->info('Successfully deleted ' . count($deleteParams) . ' records.');
                    }
                }

                // Fetch next batch of results using scroll
                $response = $client->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => '1m'
                ]);

            } while (count($hits) > 0);

            $this->info('Completed deleting records.');

        } catch (\Exception $e) {
            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.1',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process:delete-cached-data-with-elasticsearch',
                    'error' => 'Error deleting records: ' . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);

        }
    }
}
