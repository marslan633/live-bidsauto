<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elasticsearch\Client;

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
    protected $description = 'Delete records with status "completed" from Elasticsearch index "vehicle_archived_api_data"';

    /**
     * The Elasticsearch client instance.
     *
     * @var Client
     */

    /**
     * Create a new command instance.
     *
     * @param Client $client
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
        $this->info('Starting to delete completed status records from vehicle_archived_api_data...');
        $client = app('ElasticsearchKvmOne');
        try {
            // Search for documents with status 'completed'
            $params = [
                'index' => 'vehicle_archived_api_data',
                'body'  => [
                    'query' => [
                        'match' => [
                            'status' => 'completed'
                        ]
                    ]
                ]
            ];

            $results = $client->search($params);

            // If no records found, return a message
            if ($results['hits']['total']['value'] == 0) {
                $this->info('No records found with status "completed".');
                return;
            }

            // Iterate over the results and delete them
            foreach ($results['hits']['hits'] as $hit) {
                $client->delete([
                    'index' => 'vehicle_archived_api_data',
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
