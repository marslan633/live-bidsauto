<?php
namespace App\Console\Commands\KvmFour;

use Illuminate\Console\Command;
use Elasticsearch\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeleteExpiredData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:delete-expired-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete records where sale_date is less than the current date from Elasticsearch index "vehicle_records"';

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
        $this->info('Starting to delete records where sale_date is less than the current date from vehicle_records...');

        // Elasticsearch client
        $client = app('ElasticsearchKvmFour');
        $clientKvmOne = app('ElasticsearchKvmOne');

        // Get current date and time (in UTC)
        $now = Carbon::now()->utc(); // Ensure you're using UTC to match Elasticsearch
        $currentDateTime = $now->toIso8601String(); // Current date and time in ISO8601 format (e.g., 2025-05-10T18:35:01+00:00)

        // Log the current time for debugging purposes
        Log::info('Deleting records where sale_date < current date and time', [
            'now' => $now->toDateTimeString(),
            'current_date_time' => $currentDateTime,
        ]);

        try {
            // Initial search query to get the first batch of results
            $params = [
                'index' => 'vehicle_records',
                'scroll' => '1m', // Keep the scroll context open for 1 minute
                'size' => 1000, // Fetch 1000 records at a time
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'range' => [
                                        'sale_date' => [
                                            'lt' => $currentDateTime // Delete records where sale_date < current date and time
                                        ]
                                    ]
                                ],
                                [
                                    'term' => [
                                        'data_source' => 1 // Match records with data_source = 1
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];


            // Perform the search query to get the first batch
            $response = $client->search($params);
            $scrollId = $response['_scroll_id'];

            // Continue scrolling and deleting until no more results are returned
            do {
                $hits = $response['hits']['hits'];

                if (count($hits) == 0) {
                    break;
                }

                // Prepare delete operations for the current batch
                $deleteParams = [];
                foreach ($hits as $hit) {
                    $deleteParams[] = [
                        'delete' => [
                            '_index' => 'vehicle_records',
                            '_id' => $hit['_id']
                        ]
                    ];
                }

                // Perform bulk delete
                if (!empty($deleteParams)) {
                    $bulkResponse = $client->bulk(['body' => $deleteParams]);

                    if (isset($bulkResponse['errors']) && $bulkResponse['errors']) {
                        $clientKvmOne->index([
                            'index' => 'error_logs',
                            'body' => [
                                'server_name' => 'KVM4.4',
                                'error_type' => 'Internal Server Error',
                                'command_name' => 'process:delete-expired-data',
                                'error' => 'Bulk delete errors: ' . json_encode($bulkResponse),
                                'created_at' => now()->toIso8601String(),
                                'updated_at' => now()->toIso8601String(),
                            ],
                        ]);

                    } else {
                        $this->info('Successfully deleted ' . count($deleteParams) . ' records.');
                    }
                }

                // Fetch the next batch of results using scroll
                $response = $client->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => '1m' // Keep scrolling for 1 minute
                ]);

            } while (count($hits) > 0);

            $this->info('Completed deleting records.');

        } catch (\Exception $e) {
            $clientKvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.4',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process:delete-expired-data',
                    'error' => 'Error deleting records: ' . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }
}
