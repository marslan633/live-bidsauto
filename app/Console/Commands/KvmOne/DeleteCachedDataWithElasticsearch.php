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
    protected $description = 'Delete records with status "pending" and older than 30 minutes from Elasticsearch index "vehicle_process_cached_api_data"';

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
        $this->info('Checking total pending records older than 30 minutes...');

        $client = app('ElasticsearchKvmOne');
        $now = Carbon::now()->utc();
        $cutoffTime = $now->subMinutes(30)->toDateTimeString(); // 30 mins ago

        // Step 1: Get total matching records
        $countResponse = $client->count([
            'index' => 'vehicle_process_cached_api_data',
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['status' => 'pending']]
                        ],
                        'filter' => [
                            ['range' => ['updated_at' => ['lte' => $cutoffTime]]]
                        ]
                    ]
                ]
            ]
        ]);

        $totalRecords = $countResponse['count'] ?? 0;
        $this->info("Total matching records: $totalRecords");

        if ($totalRecords <= 200) {
            $this->info("Nothing to delete. 200 or fewer records found.");
            return;
        }

        $recordsToDelete = $totalRecords - 200;
        $this->info("Preparing to delete $recordsToDelete records...");

        // Step 2: Search and delete only $recordsToDelete using scroll
        $params = [
            'index' => 'vehicle_process_cached_api_data',
            'scroll' => '1m',
            'size' => 300,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['status' => 'pending']]
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
                            '_index' => 'vehicle_process_cached_api_data',
                            '_id' => $hit['_id']
                        ]
                    ];
                    $deletedCount++;
                }
            }

            if (!empty($deleteParams)) {
                $bulkResponse = $client->bulk(['body' => $deleteParams]);
                $this->info("Deleted " . count($deleteParams) . " documents.");
            }

            if ($deletedCount >= $recordsToDelete) break;

            $response = $client->scroll([
                'scroll_id' => $scrollId,
                'scroll' => '1m'
            ]);

        } while (!empty($response['hits']['hits']));

        $this->info("Completed deletion. Total deleted: $deletedCount");
    }

}
