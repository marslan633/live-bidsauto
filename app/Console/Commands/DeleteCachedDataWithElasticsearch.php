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
    protected $description = 'Delete records older than 30 minutes from Elasticsearch index "vehicle_api_data"';

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
        $this->info('Starting to delete records older than 30 minutes from vehicle_api_data...');

        // Elasticsearch client
        $client = app('ElasticsearchKvmOne');

        // Time range: 30 minutes ago to now
        $now = Carbon::now()->utc();
        $startTime = $now->subMinutes(30)->toDateTimeString(); // 30 minutes ago

        Log::info('Deleting records older than 30 minutes', ['start' => $startTime]);

        try {
            // Search for documents older than 30 minutes
            $params = [
                'index' => 'vehicle_api_data',
                'body'  => [
                    'query' => [
                        'range' => [
                            'updated_at' => [
                                'lte' => $startTime // Delete records older than 30 minutes
                            ]
                        ]
                    ]
                ]
            ];

            $response = $client->search($params);
            Log::info('Elasticsearch response', ['response' => json_encode($response)]);

            $hitCount = $response['hits']['total']['value'] ?? 0;

            if ($hitCount == 0) {
                $this->info('No records found older than 30 minutes.');
                return;
            }

            // Prepare delete operations
            $deleteParams = [];
            foreach ($response['hits']['hits'] as $hit) {
                $deleteParams[] = [
                    'delete' => [
                        '_index' => 'vehicle_api_data',
                        '_id' => $hit['_id']
                    ]
                ];
            }

            Log::info('Preparing to delete', ['deleteCount' => count($deleteParams)]);

            // Perform bulk delete
            if (!empty($deleteParams)) {
                $bulkResponse = $client->bulk(['body' => $deleteParams]);

                if (isset($bulkResponse['errors']) && $bulkResponse['errors']) {
                    Log::error('Bulk delete errors', ['response' => json_encode($bulkResponse)]);
                } else {
                    $this->info('Successfully deleted records.');
                }
            }

        } catch (\Exception $e) {
            Log::error('Error deleting records: ' . $e->getMessage());
            $this->error('Error deleting records: ' . $e->getMessage());
        }
    }
}
