<?php

namespace App\Console\Commands;

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
    protected $description = 'Delete records with status "completed" from Elasticsearch index "vehicle_api_data" within a time range of 30 minutes to 1 hour ago';

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
        $this->info('Starting to delete completed status records from vehicle_api_data within the time range of 30 minutes to 1 hour ago...');

        // Elasticsearch client
        $client = app('ElasticsearchKvmOne');

        // Time range: 1 hour ago to 30 minutes ago
        $now = Carbon::now();
        $startTime = $now->subMinutes(60)->toDateTimeString();
        $endTime = $now->subMinutes(30)->toDateTimeString();

        try {
            // Search for documents with status 'completed' and within the time range
            $params = [
                'index' => 'vehicle_api_data',
                'body'  => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'term' => [
                                        'status' => 'completed'  // Changed to 'term' for exact match
                                    ]
                                ]
                            ],
                            'filter' => [
                                [
                                    'range' => [
                                        'updated_at' => [
                                            'gte' => $startTime,
                                            'lte' => $endTime
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // Execute search query
            $response = $client->search($params);

            // Get the total number of hits
            $hitCount = isset($response['hits']['total']['value']) ? $response['hits']['total']['value'] : $response['hits']['total'];

            if ($hitCount == 0) {
                $this->info('No records found with status "completed" within the time range.');
                return;
            }

            // Prepare bulk delete request
            $deleteParams = [];
            foreach ($response['hits']['hits'] as $hit) {
                $deleteParams[] = [
                    'delete' => [
                        '_index' => 'vehicle_api_data',
                        '_id' => $hit['_id']
                    ]
                ];
            }

            // Perform bulk delete
            if (!empty($deleteParams)) {
                $bulkResponse = $client->bulk(['body' => $deleteParams]);

                // Log the result of the bulk delete operation
                if (isset($bulkResponse['errors']) && $bulkResponse['errors']) {
                    $this->error('Bulk delete encountered errors: ' . json_encode($bulkResponse['items']));
                } else {
                    $this->info('Deleted ' . count($deleteParams) . ' records.');
                }
            }

        } catch (\Exception $e) {
            Log::error('Error deleting records: ' . $e->getMessage());
            $this->error('Error deleting records: ' . $e->getMessage());
        }
    }
}
