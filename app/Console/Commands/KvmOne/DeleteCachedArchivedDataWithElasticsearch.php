<?php

namespace App\Console\Commands\KvmOne;

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
    protected $description = 'Delete records with status "pending" from Elasticsearch index "vehicle_archived_api_data" older than 30 minutes to 1 hour ago';

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
        $this->info('Starting to delete pending records older than 30 to 60 minutes from vehicle_archived_api_data...');

        $client = app('ElasticsearchKvmOne');

        $now = Carbon::now()->utc();
        $startTime = $now->copy()->subMinutes(60)->toIso8601String(); // 60 mins ago
        $endTime = $now->copy()->subMinutes(30)->toIso8601String();   // 30 mins ago

        Log::info('Checking records to delete', [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        try {
            // Step 1: Count matching records
            $countResponse = $client->count([
                'index' => 'vehicle_archived_api_data',
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['match' => ['status' => 'pending']]
                            ],
                            'filter' => [
                                ['range' => [
                                    'updated_at' => [
                                        'gte' => $startTime,
                                        'lte' => $endTime
                                    ]
                                ]]
                            ]
                        ]
                    ]
                ]
            ]);

            $totalRecords = $countResponse['count'] ?? 0;
            $this->info("Total records matching: $totalRecords");

            if ($totalRecords <= 200) {
                $this->info("Nothing to delete. 200 or fewer records found.");
                return;
            }

            $recordsToDelete = $totalRecords - 200;
            $this->info("Preparing to delete $recordsToDelete records...");

            // Step 2: Search matching documents
            $params = [
                'index' => 'vehicle_archived_api_data',
                'scroll' => '1m',
                'size' => 300,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['match' => ['status' => 'pending']]
                            ],
                            'filter' => [
                                ['range' => [
                                    'updated_at' => [
                                        'gte' => $startTime,
                                        'lte' => $endTime
                                    ]
                                ]]
                            ]
                        ]
                    ],
                    'sort' => [
                        ['updated_at' => ['order' => 'asc']]
                    ]
                ]
            ];

            $deletedCount = 0;
            $response = $client->search($params);
            $scrollId = $response['_scroll_id'] ?? null;

            do {
                $hits = $response['hits']['hits'];

                if (empty($hits)) break;

                $deleteParams = [];

                foreach ($hits as $hit) {
                    if ($deletedCount >= $recordsToDelete) break;

                    if (!empty($hit['_id'])) {
                        $deleteParams[] = [
                            'delete' => [
                                '_index' => 'vehicle_archived_api_data',
                                '_id' => $hit['_id']
                            ]
                        ];
                        $deletedCount++;
                    }
                }

                if (!empty($deleteParams)) {
                    $bulkResponse = $client->bulk(['body' => $deleteParams]);

                    if (isset($bulkResponse['errors']) && $bulkResponse['errors']) {
                        $client->index([
                            'index' => 'error_logs',
                            'body' => [
                                'server_name' => 'KVM4.1',
                                'error_type' => 'Internal Server Error',
                                'command_name' => 'process:delete-cached-archived-data-with-elasticsearch',
                                'error' => 'Bulk delete errors: ' . json_encode($bulkResponse),
                                'created_at' => now()->toIso8601String(),
                                'updated_at' => now()->toIso8601String(),
                            ],
                        ]);
                    } else {
                        $this->info("Deleted " . count($deleteParams) . " records.");
                    }
                }

                if ($deletedCount >= $recordsToDelete) break;

                $response = $client->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => '1m'
                ]);

            } while (!empty($response['hits']['hits']));

            $this->info("Completed deletion. Total deleted: $deletedCount");

        } catch (\Exception $e) {
            $client->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.1',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process:delete-cached-archived-data-with-elasticsearch',
                    'error' => 'Error deleting records: ' . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }

}
