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
        Log::info('Checking total completed records older than 30 minutes in vehicle_archived_api_data...');

        $client = app('ElasticsearchKvmOne');
        $now = Carbon::now()->utc();
        $cutoffTime = $now->subMinutes(180)->toIso8601String();

        // Step 1: Get total matching records
        $countResponse = $client->count([
            'index' => 'vehicle_archived_api_data',
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['status' => 'completed']]
                        ],
                        'filter' => [
                            ['range' => ['updated_at' => ['lte' => $cutoffTime]]]
                        ]
                    ]
                ]
            ]
        ]);

        $totalRecords = $countResponse['count'] ?? 0;
        Log::info("Total matching records: " . $totalRecords);

        if ($totalRecords <= 200) {
            Log::info("Nothing to delete. 200 or fewer records found.");
            return;
        }

        $recordsToDelete = $totalRecords - 200;
        Log::info("Preparing to delete " . $recordsToDelete . " records...");

        // Step 2: Search and delete using scroll
        $params = [
            'index' => 'vehicle_archived_api_data',
            'scroll' => '1m',
            'size' => 300,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['status' => 'completed']]
                        ],
                        'filter' => [
                            ['range' => ['updated_at' => ['lte' => $cutoffTime]]]
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

            if (empty($hits)) {
                break;
            }

            $deleteParams = [];

            foreach ($hits as $hit) {
                if ($deletedCount >= $recordsToDelete) break;

                if (isset($hit['_id'])) {
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
                Log::info("Deleted " . count($deleteParams) . " documents.");
            }

            if ($deletedCount >= $recordsToDelete) break;

            $response = $client->scroll([
                'scroll_id' => $scrollId,
                'scroll' => '1m'
            ]);

        } while (!empty($response['hits']['hits']));

        Log::info("Completed deletion. Total deleted: " . $deletedCount);
    }


}