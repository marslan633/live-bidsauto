<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elasticsearch\Client;
use Carbon\Carbon;

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

        try {
            // Calculate time range
            $now = Carbon::now();
            $startTime = $now->subMinutes(60)->toDateTimeString(); // 1 hour ago
            $endTime = $now->subMinutes(30)->toDateTimeString(); // 30 minutes ago

            // Search for documents with status 'completed' and time range
            $params = [
                'index' => 'vehicle_api_data',
                'body'  => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                // Match status 'completed'
                                [
                                    'match' => [
                                        'status' => 'completed'
                                    ]
                                ]
                            ],
                            'filter' => [
                                // Range filter for created_at or updated_at
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
            ];

            $results = $client->search($params);

            // If no records found, return a message
            if ($results['hits']['total']['value'] == 0) {
                $this->info('No records found with status "completed" within the time range.');
                return;
            }

            // Iterate over the results and delete them
            foreach ($results['hits']['hits'] as $hit) {
                $client->delete([
                    'index' => 'vehicle_api_data',
                    'id'    => $hit['_id']
                ]);

                $this->info('Deleted record with ID: ' . $hit['_id']);
            }

            $this->info('Completed deleting records.');

        } catch (\Exception $e) {
            $this->error('Error deleting records: ' . $e->getMessage());
        }
    }
}
