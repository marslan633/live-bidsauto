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
