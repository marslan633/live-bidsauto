<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class SetupElasticsearchClassesKvmOne extends Command
{
    protected $signature = 'process:setup-elasticsearch-classes-kvm-one';
    protected $description = 'Delete existing Elasticsearch indexes if they exist, then create them';

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
            // ... (other indices, same as your original)
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
            'error_logs' => [
                'mappings' => [
                    'properties' => [
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis'
                        ],
                        'updated_at' => [
                            'type' => 'date',
                            'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis'
                        ],
                        'server_name' => ['type' => 'keyword'],
                        'error_type' => ['type' => 'keyword'],
                        'command_name' => ['type' => 'keyword'],
                        'error' => ['type' => 'text'],
                    ],
                ],
            ],
        ];

        foreach ($indices as $indexName => $indexConfig) {
            try {
                // Check if index exists
                $exists = $client->indices()->exists(['index' => $indexName]);

                if ($exists) {
                    $this->info("Index '{$indexName}' exists. Attempting to delete...");

                    // Delete index
                    $client->indices()->delete(['index' => $indexName]);
                    $this->info("Index '{$indexName}' deleted successfully.");
                } else {
                    $this->info("Index '{$indexName}' does NOT exist.");
                }

                // Create index
                $client->indices()->create([
                    'index' => $indexName,
                    // Add settings if provided
                    'body' => $indexConfig,
                ]);
                $this->info("Index '{$indexName}' created successfully.");
            } catch (ClientResponseException $e) {
                $this->error("Elasticsearch Client error for index '{$indexName}': " . $e->getMessage());
            } catch (\Exception $e) {
                $this->error("General error for index '{$indexName}': " . $e->getMessage());
            }
        }
    }
}
