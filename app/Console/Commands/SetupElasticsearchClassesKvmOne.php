<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
            // Check if index exists
            $exists = $client->indices()->exists(['index' => $indexName]);
            $this->info("Exists response for index {$indexName}: " . json_encode($exists));
            if ($exists) {
                $this->info("Index '{$indexName}' already exists. Skipping creation.");
            } else {
                // Create index
                $params = ['index' => $indexName];

                // Add mappings and settings if provided
                if (isset($indexConfig['mappings'])) {
                    $params['body']['mappings'] = $indexConfig['mappings'];
                }
                if (isset($indexConfig['settings'])) {
                    $params['body']['settings'] = $indexConfig['settings'];
                }

                $client->indices()->create($params);
                $this->info("Index '{$indexName}' created successfully.");
            }
        }
    }
}
