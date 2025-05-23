<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class SetupElasticsearchClassesKvmFour extends Command
{
    protected $signature = 'process:setup-elasticsearch-classes-kvm-four';
    protected $description = 'Create Elasticsearch indexes for KvmFour if they do not exist';

    public function handle()
    {
        $client = app('ElasticsearchKvmFour');

        $indices = [
            'vehicle_records' => [
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'manufacturer_id' => ['type' => 'integer'],
                        'vehicle_model_id' => ['type' => 'integer'],
                        'lot_id' => ['type' => 'keyword'],
                        'vin' => ['type' => 'keyword'],
                        'year' => ['type' => 'integer'],
                        'odometer_mi' => ['type' => 'integer'],
                        'buy_now_id' => ['type' => 'integer'],
                        'sale_date' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||yyyy-MM-dd'
                        ],
                        'domain_id' => ['type' => 'integer'],
                        'condition_id' => ['type' => 'integer'],
                        'fuel_id' => ['type' => 'integer'],
                        'seller_type_id' => ['type' => 'integer'],
                        'drive_wheel_id' => ['type' => 'integer'],
                        'transmission_id' => ['type' => 'integer'],
                        'detailed_title_id' => ['type' => 'integer'],
                        'damage_id' => ['type' => 'integer'],
                    ],
                ],
            ],

            'vehicle_record_archiveds' => [
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'manufacturer_id' => ['type' => 'integer'],
                        'vehicle_model_id' => ['type' => 'integer'],
                        'year' => ['type' => 'integer'],
                        'lot_id' => ['type' => 'keyword'],
                        'vin' => ['type' => 'keyword'],
                        'odometer_mi' => ['type' => 'integer'],
                        'buy_now_id' => ['type' => 'integer'],
                        'sale_date' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||yyyy-MM-dd'
                        ],
                        'final_bid_updated_at' => ['type' => 'keyword'],
                        'year_id' => ['type' => 'integer', 'null_value' => 0],
                        'domain_id' => ['type' => 'integer'],
                        'condition_id' => ['type' => 'integer'],
                        'fuel_id' => ['type' => 'integer'],
                        'seller_type_id' => ['type' => 'integer'],
                        'drive_wheel_id' => ['type' => 'integer'],
                        'transmission_id' => ['type' => 'integer'],
                        'detailed_title_id' => ['type' => 'integer'],
                        'damage_id' => ['type' => 'integer'],
                    ],
                ],
            ],

            'sale_auction_histories' => [
                'mappings' => [
                    'properties' => [
                        'vin' => ['type' => 'keyword'],
                        'domain_id' => ['type' => 'integer'],
                        'sale_date' => ['type' => 'keyword'],
                        'lot_id' => ['type' => 'integer'],
                        'bid' => ['type' => 'float'],
                        'odometer_mi' => ['type' => 'integer'],
                        'status_id' => ['type' => 'integer'],
                        'seller_id' => ['type' => 'integer'],
                        'created_at' => ['type' => 'keyword'],
                        'updated_at' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];

        foreach ($indices as $indexName => $indexConfig) {
            try {
                $exists = $client->indices()->exists(['index' => $indexName]);
                $this->info("Checking if index '{$indexName}' exists: " . ($exists ? 'Yes' : 'No'));

                if (!$exists) {
                    $params = ['index' => $indexName];

                    // Always initialize 'body' as array to avoid undefined errors
                    $params['body'] = [];

                    if (isset($indexConfig['mappings'])) {
                        $params['body']['mappings'] = $indexConfig['mappings'];
                    }
                    if (isset($indexConfig['settings'])) {
                        $params['body']['settings'] = $indexConfig['settings'];
                    }

                    $this->info("Creating index '{$indexName}' with params:");
                    $this->info(json_encode($params, JSON_PRETTY_PRINT));

                    $response = $client->indices()->create($params);

                    $this->info("Index '{$indexName}' created successfully.");
                    $this->info("Response: " . json_encode($response));
                } else {
                    $this->info("Index '{$indexName}' already exists. Skipping creation.");
                }
            } catch (ClientResponseException $e) {
                $this->error("ClientResponseException while creating '{$indexName}': " . $e->getMessage());
                if (str_contains($e->getMessage(), 'resource_already_exists_exception')) {
                    $this->info("Index '{$indexName}' already exists (caught during create). Skipping.");
                } else {
                    throw $e;
                }
            } catch (\Exception $e) {
                $this->error("Exception while creating '{$indexName}': " . $e->getMessage());
                throw $e;
            }
        }
    }
}
