<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class SetupElasticsearchClassesKvmOne extends Command
{
    protected $signature = 'process:setup-elasticsearch-classes-kvm-one';
    protected $description = 'Create Elasticsearch indexes if they do not exist';

    public function handle()
    {
        $client = app('ElasticsearchKvmOne');

        $indices = [
            'vehicle_api_data' => [
                'mappings' => [
                    'properties' => [
                        'cache_value' => ['type' => 'text'],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis'
                        ],
                        'expires_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis'
                        ],
                    ]
                ]
            ],

            'vehicle_process_cached_api_data' => [
                'mappings' => [
                    'properties' => [
                        'cache_value' => ['type' => 'text'],
                        'created_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                        'updated_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                        'expires_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                        'status' => ['type' => 'keyword'],
                    ]
                ]
            ],

            'vehicle_archived_api_data' => [
                'mappings' => [
                    'properties' => [
                        'cache_value' => ['type' => 'text'],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis'
                        ],
                        'updated_at' => ['type' => 'date'],
                        'expires_at' => ['type' => 'date'],
                        'status' => ['type' => 'keyword'],
                    ]
                ]
            ],

            'cron_run_histories' => [
                'settings' => [
                    'index' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'cron_name' => ['type' => 'keyword'],
                        'start_time' => ['type' => 'date'],
                        'end_time' => ['type' => 'date'],
                        'status' => ['type' => 'keyword'],
                        'error_message' => ['type' => 'text'],
                    ],
                ],
            ],
        ];

        foreach ($indices as $indexName => $indexConfig) {
            $exists = $client->indices()->exists(['index' => $indexName]);
            $this->info("Exists response for index {$indexName}: " . json_encode($exists));

            // Check if $exists is an object and empty (means index does NOT exist)
            if (is_object($exists) && count(get_object_vars($exists)) === 0) {
                $this->info("Index '{$indexName}' does NOT exist. Creating it...");

                $params = ['index' => $indexName];

                if (isset($indexConfig['mappings'])) {
                    $params['body']['mappings'] = $indexConfig['mappings'];
                }
                if (isset($indexConfig['settings'])) {
                    $params['body']['settings'] = $indexConfig['settings'];
                }

                try {
                    $client->indices()->create($params);
                    $this->info("Index '{$indexName}' created successfully.");
                } catch (ClientResponseException $e) {
                    if (str_contains($e->getMessage(), 'resource_already_exists_exception')) {
                        $this->info("Index '{$indexName}' already exists (caught during create). Skipping.");
                    } else {
                        throw $e;
                    }
                }

            } else {
                // If not empty object, treat as index exists
                $this->info("Index '{$indexName}' already exists. Skipping creation.");
            }

        }
    }
}
