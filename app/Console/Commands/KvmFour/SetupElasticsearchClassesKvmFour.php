<?php

namespace App\Console\Commands\KvmFour;

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
                'settings' => [
                    'index' => [
                        'max_result_window' => 50000,
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'manufacturer_id' => ['type' => 'integer'],
                        'vehicle_model_id' => ['type' => 'integer'],
                        'lot_id' => ['type' => 'keyword'],
                        'vin' => ['type' => 'keyword'],
                        'year' => ['type' => 'integer'],
                        'odometer_mi' => ['type' => 'integer'],
                        'data_source' => ['type' => 'integer'],
                        'buy_now_id' => ['type' => 'integer'],
                        'sale_date' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||yyyy-MM-dd'
                        ],
                        'bid' => [
                            'type' => 'double',
                            'null_value'=> 0
                        ],
                        'domain_id' => ['type' => 'integer'],
                        'condition_id' => ['type' => 'integer'],
                        'fuel_id' => ['type' => 'integer'],
                        'seller_type_id' => ['type' => 'integer'],
                        'drive_wheel_id' => ['type' => 'integer'],
                        'transmission_id' => ['type' => 'integer'],
                        'detailed_title_id' => ['type' => 'integer'],
                        'damage_id' => ['type' => 'integer'],
                        // New Mappings
                        'actual_cash_value' => ['type' => 'keyword'],
                        'airbags' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'api_id' => ['type' => 'long'],
                        'body_type_id' => ['type' => 'long'],
                        'clean_wholesale_price' => ['type' => 'long'],
                        'color_id' => ['type' => 'long'],
                        'cylinders' => ['type' => 'long'],
                        'engine_id' => ['type' => 'long'],
                        'estimate_repair_price' => ['type' => 'long'],
                        'final_bid' => ['type' => 'long'],
                        'generation_id' => ['type' => 'long'],
                        'image_id' => ['type' => 'long'],
                        'is_new' => ['type' => 'long'],
                        'keys_available' => ['type' => 'long'],
                        'location_id' => ['type' => 'long'],
                        'odometer_id' => ['type' => 'long'],
                        'odometer_km' => ['type' => 'long'],
                        'salvage_id' => ['type' => 'long'],
                        'seller_id' => ['type' => 'long'],
                        'status_id' => ['type' => 'long'],
                        'vehicle_type_id' => ['type' => 'long'],
                        'year_id' => ['type' => 'long'],
                        'buy_now_updated_at' => ['type' => 'keyword'], // Before => date
                        'final_bid_updated_at' => ['type' => 'keyword'], // Before => date
                        'sale_date_updated_at' => ['type' => 'keyword'], // Before => date
                        'bid_updated_at' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'external_id' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'grade_iaai' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'odometer_status' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'pre_accident_price' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'processed_at' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'title' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'tags' => ['type' => 'keyword'],
                        'line' => ['type' => 'keyword'],
                        'archived_at' => ['type' => 'keyword'],
                        'is_timed_auction' => ['type' => 'long'],
                        'seller_reserve' => ['type' => 'keyword'],
                        'auction_type_id' => ['type' => 'long'], 
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
                        'final_bid_updated_at' => ['type' => 'keyword'],
                        'odometer_mi' => ['type' => 'integer'],
                        'status_id' => ['type' => 'integer'],
                        'seller_id' => ['type' => 'integer'],
                        'created_at' => ['type' => 'keyword'],
                        'updated_at' => ['type' => 'keyword'],
                        'data_source' => ['type' => 'keyword'], 
                        'coming_from' => ['type' => 'keyword'], 
                        'archived_at' => ['type' => 'keyword'],
                    ],
                ],
            ],

            'currency_exchanges' => [
                'mappings' => [
                    'properties' => [
                        'base_code' => ['type' => 'keyword'],
                        'last_update_at' => ['type' => 'keyword'],
                        'bgn' => ['type' => 'float'],
                        'eur' => ['type' => 'float'],
                        'usd' => ['type' => 'float'],
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