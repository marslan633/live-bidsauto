<?php
namespace App\Console\Commands;

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

        // Get current date and time (in UTC)
        $now = Carbon::now()->utc(); // Ensure you're using UTC to match Elasticsearch
        $currentDateTime = $now->toIso8601String(); // Current date and time in ISO8601 format (e.g., 2025-05-10T18:35:01+00:00)

        // Log the current time for debugging purposes
        Log::info('Deleting records where sale_date < current date and time', [
            'now' => $now->toDateTimeString(),
            'current_date_time' => $currentDateTime,
        ]);

        try {
            // Pagination settings
            $pageSize = 1000; // Number of records per page
            $from = 0; // Start from the first record

            // Continue fetching records in pages until no more are returned
            do {
                // Search for documents where sale_date is less than the current date and time
                $params = [
                    'index' => 'vehicle_records',
                    'size' => $pageSize, // Fetch 1000 records at a time
                    'from' => $from, // Paginate by 'from' value
                    'body' => [
                        'query' => [
                            'range' => [
                                'sale_date' => [
                                    'lt' => $currentDateTime // Delete records where sale_date < current date and time
                                ]
                            ]
                        ]
                    ]
                ];

                // Perform search query
                $response = $client->search($params);
                Log::info('Elasticsearch response', ['response' => json_encode($response)]);

                // Check if there are any records to delete
                $hits = $response['hits']['hits'];

                if (count($hits) == 0) {
                    break;
                }

                // Prepare delete operations
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
                        Log::error('Bulk delete errors', ['response' => json_encode($bulkResponse)]);
                    } else {
                        $this->info('Successfully deleted ' . count($deleteParams) . ' records.');
                    }
                }

                // Increment the 'from' value for the next page of records
                $from += $pageSize;

            } while (count($hits) > 0);

            $this->info('Completed deleting records.');

        } catch (\Exception $e) {
            Log::error('Error deleting records: ' . $e->getMessage());
            $this->error('Error deleting records: ' . $e->getMessage());
        }
    }
}
