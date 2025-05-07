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
    protected $description = 'Delete records with status "completed" from Elasticsearch index "vehicle_archived_api_data" within a time range of 30 minutes to 1 hour ago';

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
        $this->info('Starting to delete completed status records from vehicle_archived_api_data within the time range of 30 minutes to 1 hour ago...');

        // Elasticsearch client
        $client = app('ElasticsearchKvmOne');

        // Time range: 1 hour ago to 30 minutes ago
        $now = Carbon::now();
        $startTime = $now->subMinutes(60)->toDateTimeString();
        $endTime = $now->subMinutes(30)->toDateTimeString();

        try {
            // Initial search query with scroll
            $scrollTime = '1m'; // The scroll context will last for 1 minute
            $response = $client->search([
                'index' => 'vehicle_archived_api_data',
                'scroll' => $scrollTime,
                'size' => 200,  // Adjust the size as needed
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'match' => [
                                        'status' => 'completed'
                                    ]
                                ]
                            ],
                            'filter' => [
                                [
                                    'range' => [
                                        'created_at' => [
                                            'gte' => $startTime,  // 1 hour ago
                                            'lte' => $endTime    // 30 minutes ago
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            $scrollId = $response['_scroll_id'];

            // Continue fetching and deleting documents until there are no more hits
            do {
                $hits = $response['hits']['hits'];
                if (count($hits) == 0) {
                    break;
                }

                // Prepare bulk delete params
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
                    $client->bulk(['body' => $deleteParams]);
                    $this->info('Deleted ' . count($deleteParams) . ' records.');
                }

                // Fetch next batch of results using scroll
                $response = $client->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => $scrollTime
                ]);

            } while (count($hits) > 0);

            $this->info('Completed deleting records.');

        } catch (\Exception $e) {
            Log::error('Error deleting records: ' . $e->getMessage());
            $this->error('Error deleting records: ' . $e->getMessage());
        }
    }
}
