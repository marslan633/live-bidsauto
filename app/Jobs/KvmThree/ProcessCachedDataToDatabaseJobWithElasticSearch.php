<?php

namespace App\Jobs\KvmThree;

use App\Models\VehicleProcessCachedApiData;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProcessCachedDataToDatabaseJobWithElasticSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $cacheKey;

    /**
     * Create a new job instance.
     */
    public function __construct($cacheKey)
    {
        $this->queue = 'process_cached_data_to_database_job_with_elasticsearch';
        $this->cacheKey = $cacheKey;
        Log::info('Log From Database Constructor');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $clientkvmOne = app('ElasticsearchKvmOne');
        try {

            $data = unCompressData($this->cacheKey->cache_value);
            if (!$data) {
                $clientkvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'General',
                        'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                        'error' => "No data found for key: {$this->cacheKey->_id}",
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
                return;
            }

            $batchData = [];
            foreach ($data as $car) {
                $preparedData = $this->prepareCarData((array) $car);

                if($preparedData){
                    $batchData[] = $preparedData;
                }
            }

            if (count($batchData) > 0) {
                Log::info('Batch Inserted');
                $this->insertBatch($batchData, $this->cacheKey->_id);
                $batchData = []; // Reset batch
            }else{
                // $clientkvmOne->index([
                //     'index' => 'error_logs',
                //     'body' => [
                //         'server_name' => 'KVM4.3',
                //         'error_type' => 'General',
                //         'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                //         'error' => "Batch Condition Not Meet",
                //         'created_at' => now()->toIso8601String(),
                //         'updated_at' => now()->toIso8601String(),
                //     ],
                // ]);
            }

        } catch (\Exception $e) {
            $clientkvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                    'error' => "Error processing key {$this->cacheKey->_id}: " . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }

         /**
     * ✅ Insert batch of processed data
     */
    public function insertBatch(array $batchData, $cacheKey)
    {
        Log::info('Starting Batch Insertion');
        $clientkvmOne = app('ElasticsearchKvmOne');
        try {
            if (empty($batchData)) {
                return;
            }


            // Extract API IDs from batchData
            $apiIds = array_column($batchData, 'api_id');

            // Fetch existing records by API ID
            $existingRecords = DB::table('vehicle_records')->whereIn('api_id', $apiIds)->pluck('id', 'api_id');

            // Lists for new and updated records
            $newRecords = [];
            $updatedRecords = [];
            $failedRecords = []; // ❌ Store records that failed

            foreach ($batchData as $record) {
                try {
                    if (isset($existingRecords[$record['api_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['api_id']]; // Add ID for update
                        $record['processed_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        $updatedRecords[] = $record;
                    } else {
                        // New record - insert
                        $record['is_new'] = true;
                        $record['processed_at'] = Carbon::now();
                        $record['created_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        $newRecords[] = $record;
                    }
                } catch (\Exception $e) {
                    $failedRecords[] = $record;
                    $clientkvmOne->index([
                        'index' => 'error_logs',
                        'body' => [
                            'server_name' => 'KVM4.3',
                            'error_type' => 'Internal Server Error',
                            'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                            'error' => "Skipping record due to error: " . json_encode($e->getMessage()),
                            'created_at' => now()->toIso8601String(),
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }

            // ✅ Bulk Insert New Records
            if (!empty($newRecords)) {
                DB::table('vehicle_records')->insert($newRecords);
            }

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                foreach($updatedRecords as $item){
                    DB::table('vehicle_records')->where('id', $item['id'])->update($item);
                }
                // DB::table('vehicle_records')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
            }
        } catch (\Throwable $e) {
            $clientkvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                    'error' => "Batch insert failed: " . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
            return;
        }

        try{
            $client = app('ElasticsearchKvmOne');

            $response = $client->exists([
                'index' => 'vehicle_process_cached_api_data',
                'id' => $cacheKey,
            ]);

            if ($response) {
                try{
                    $client->update([
                        'index' => 'vehicle_process_cached_api_data',
                        'id' => $cacheKey,
                        'body' => [
                            'doc' => [
                                'status' => 'completed'
                            ]
                        ]
                    ]);
                    Log::info("✅ Elasticsearch Processed document status updated for _id: $cacheKey");
                }  catch (\Throwable $e) {
                    // $clientkvmOne->index([
                    //     'index' => 'error_logs',
                    //     'body' => [
                    //         'server_name' => 'KVM4.3',
                    //         'error_type' => 'Internal Server Error',
                    //         'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                    //         'error' => "⚠️ Failed to update document: " . json_encode($e->getMessage()),
                    //         'created_at' => now()->toIso8601String(),
                    //         'updated_at' => now()->toIso8601String(),
                    //     ],
                    // ]);
                }
            } else {
                $clientkvmOne->index([
                    'index' => 'error_logs',
                    'body' => [
                        'server_name' => 'KVM4.3',
                        'error_type' => 'General',
                        'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                        'error' => "⚠️ Document not found for update with _id: $cacheKey",
                        'created_at' => now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }
        }catch (\Throwable $e) {
            $clientkvmOne->index([
                'index' => 'error_logs',
                'body' => [
                    'server_name' => 'KVM4.3',
                    'error_type' => 'Internal Server Error',
                    'command_name' => 'process_cached_data_to_database_job_with_elasticsearch',
                    'error' => "❌ Elasticsearch exists check failed: " . json_encode($e->getMessage()),
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        }
    }

    public function prepareCarData(array $car)
    {
        // Log::info('Car Dara', ['CarData' => json_encode($car)]);
        $year = null;
        if (!isset($car['year'])) {
            $year = DB::table('years')->insertGetId(['name' => $car['year']]);
        }
        // Log::info('Year', ['data' => $year]);

        $car['vehicle_record'] = (array) $car['vehicle_record'];
        $model_id = DB::table('vehicle_models')
                ->where('vehicle_model_api_id', $car['model']['vehicle_model_api_id'])
                ->value('id') // Fetch only the 'id' column for efficiency
                ?? DB::table('vehicle_models')->insertGetId([
                    'vehicle_model_api_id' => $car['model']['vehicle_model_api_id'],
                    'name' => $car['model']['name'],
                ]);
        // Log::info('Model', ['data' => $model_id]);


        $imageRecord = $car['vehicle_record']['imageRecord'] ?? [];

        if (!isset($imageRecord['image_api_id'])) {
            $imageId = null;
        } else {
            $imageId = DB::transaction(function () use ($imageRecord) {
                $existingImage = DB::table('images')
                    ->where('image_api_id', $imageRecord['image_api_id'])
                    ->first(['id']);

                if ($existingImage) {
                    DB::table('images')
                        ->where('id', $existingImage->id)
                        ->update([
                            'small' => $imageRecord['small'] ?? [],
                            'normal' => $imageRecord['normal'] ?? [],
                            'big' => $imageRecord['big'] ?? [],
                            'downloaded' => $imageRecord['downloaded'] ?? [],
                            'exterior' => $imageRecord['exterior'] ?? [],
                            'interior' => $imageRecord['interior'] ?? [],
                            'video' => $imageRecord['video'] ?? null,
                            'video_youtube_id' => $imageRecord['video_youtube_id'] ?? null,
                            'external_panorama_url' => $imageRecord['external_panorama_url'] ?? null,
                        ]);

                    return $existingImage->id;
                }

                return DB::table('images')->insertGetId([
                    'image_api_id' => $imageRecord['image_api_id'],
                    'small' => $imageRecord['small'] ?? [],
                    'normal' => $imageRecord['normal'] ?? [],
                    'big' => $imageRecord['big'] ?? [],
                    'downloaded' => $imageRecord['downloaded'] ?? [],
                    'exterior' => $imageRecord['exterior'] ?? [],
                    'interior' => $imageRecord['interior'] ?? [],
                    'video' => $imageRecord['video'] ?? null,
                    'video_youtube_id' => $imageRecord['video_youtube_id'] ?? null,
                    'external_panorama_url' => $imageRecord['external_panorama_url'] ?? null,
                ]);
            });
        }
        // Log::info('Image', ['data' => $imageId]);


        $manufacturer_id =  DB::table('manufacturers')
                ->where('manufacturer_api_id', $car['manufacturer']['manufacturer_api_id'])
                ->value('id')
                ?? DB::table('manufacturers')->insertGetId([
                    'manufacturer_api_id' => $car['manufacturer']['manufacturer_api_id'],
                    'name' => $car['manufacturer']['name'],
                ]);
        // Log::info('Manufacturer', ['data' => $manufacturer_id]);

        $generation_id = DB::table('generations')
        ->where('generation_api_id', $car['generation']['generation_api_id'])
        ->value('id') ?? DB::table('generations')->insertGetId([
                'generation_api_id' => $car['generation']['generation_api_id'],
                'name' => $car['generation']['name'],
                'model_id' => $model_id,
            ]);

        // Log::info('Generation', ['data' => $generation_id]);

        $body_type_id = DB::table('body_types')
                ->where('body_type_api_id', $car['body_type']['body_type_api_id'])
                ->value('id')
                ?? DB::table('body_types')->insertGetId([
                    'body_type_api_id' => $car['body_type']['body_type_api_id'],
                    'name' => $car['body_type']['name'],
                ]);

        // Log::info('Body Type', ['data' => $body_type_id]);

        $color_id =  DB::table('colors')
                ->where('color_api_id', $car['color']['color_api_id'])
                ->value('id')
                ?? DB::table('colors')->insertGetId([
                    'color_api_id' => $car['color']['color_api_id'],
                    'name' => $car['color']['name'],
                ]);
        // Log::info('Color', ['data' => $color_id]);
        $engine_id =  DB::table('engines')
                ->where('engine_api_id', $car['engine']['engine_api_id'])
                ->value('id')
                ?? DB::table('engines')->insertGetId([
                    'engine_api_id' => $car['engine']['engine_api_id'],
                    'name' => $car['engine']['name'],
                ]);
                // Log::info('Engine', ['data' => $engine_id]);

        $transmission_id =  DB::table('transmissions')
                ->where('transmission_api_id', $car['transmission']['transmission_api_id'])
                ->value('id')
                ?? DB::table('transmissions')->insertGetId([
                    'transmission_api_id' => $car['transmission']['transmission_api_id'],
                    'name' => $car['transmission']['name'],
                ]);
                // Log::info('Transmission', ['data' => $transmission_id]);

        $drive_wheel_id =  DB::table('drive_wheels')
                ->where('drive_wheel_api_id', $car['drive_wheel']['drive_wheel_api_id'])
                ->value('id')
                ?? DB::table('drive_wheels')->insertGetId([
                    'drive_wheel_api_id' => $car['drive_wheel']['drive_wheel_api_id'],
                    'name' => $car['drive_wheel']['name'],
                ]);
                // Log::info('Driver Wheel', ['data' => $drive_wheel_id]);

        $vehicle_type_id = DB::table('vehicle_types')
                ->where('vehicle_type_api_id', $car['vehicle_type']['vehicle_type_api_id'])
                ->value('id')
                ?? DB::table('vehicle_types')->insertGetId([
                    'vehicle_type_api_id' => $car['vehicle_type']['vehicle_type_api_id'],
                    'name' => $car['vehicle_type']['name'],
                ]);
                // Log::info('Vehicle Type', ['data' => $vehicle_type_id]);

        $fuel_id =  DB::table('fuels')
                ->where('fuel_api_id', $car['fuel']['fuel_api_id'])
                ->value('id')
                ?? DB::table('fuels')->insertGetId([
                    'fuel_api_id' => $car['fuel']['fuel_api_id'],
                    'name' => $car['fuel']['name'],
                ]);
                // Log::info('Fuel', ['data' => $fuel_id]);

        $domain_id = isset($car['vehicle_record']['domain'])
        ?  DB::table('domains')
                ->where('domain_api_id', $car['vehicle_record']['domain']['domain_api_id'])
                ->value('id')
                ?? DB::table('domains')->insertGetId([
                    'domain_api_id' => $car['vehicle_record']['domain']['domain_api_id'],
                    'name' => $car['vehicle_record']['domain']['name'],
                ])
        : null;
                // Log::info('Domain', ['data' => $domain_id]);

        $selling_branch_id = isset($car['vehicle_record']['selling_branch'])
        ?  DB::table('selling_branches')
                ->where('selling_branch_api_id', $car['vehicle_record']['selling_branch']['selling_branch_api_id'])
                ->value('id')
                ?? DB::table('selling_branches')->insertGetId([
                    'selling_branch_api_id' => $car['vehicle_record']['selling_branch']['selling_branch_api_id'],
                    'name' => $car['vehicle_record']['selling_branch']['name'],
                    'link' => $car['vehicle_record']['selling_branch']['link'],
                    'number' => $car['vehicle_record']['selling_branch']['number'],
                    'domain_id' => $domain_id, // Use the computed domain_id
                ])
        : null;
        // Log::info('Seller Branch', ['data' => $selling_branch_id]);

        $odometer_id = DB::table('odometers')
            ->where('name', $car['vehicle_record']['odometer']['name'])
            ->value('id')
            ?? DB::table('odometers')->insertGetId(['name' => $car['vehicle_record']['odometer']['name']]);
        // Log::info('Odometer', ['data' => $odometer_id]);

        $seller_id = DB::table('sellers')
            ->where('seller_api_id', $car['vehicle_record']['seller']['seller_api_id'])
            ->value('id')
            ?? DB::table('sellers')->insertGetId([
                'seller_api_id' => $car['vehicle_record']['seller']['seller_api_id'],
                'name' => $car['vehicle_record']['seller']['name']
            ]);
            // Log::info('Seller', ['data' => $seller_id]);

        $seller_type_id = DB::table('seller_types')
            ->where('seller_type_api_id', $car['vehicle_record']['seller_type']['seller_type_api_id'])
            ->value('id')
            ?? DB::table('seller_types')->insertGetId([
                'seller_type_api_id' => $car['vehicle_record']['seller_type']['seller_type_api_id'],
                'name' => $car['vehicle_record']['seller_type']['name']
            ]);
            // Log::info('Seller Type', ['data' => $seller_type_id]);

        $condition_id = DB::table('conditions')
            ->where('condition_api_id', $car['vehicle_record']['condition']['condition_api_id'])
            ->value('id')
            ?? DB::table('conditions')->insertGetId([
                'condition_api_id' => $car['vehicle_record']['condition']['condition_api_id'],
                'name' => $car['vehicle_record']['condition']['name']
            ]);
            // Log::info('Condition', ['data' => $condition_id]);

        $status_id = DB::table('statuses')
            ->where('status_api_id', $car['vehicle_record']['status']['status_api_id'])
            ->value('id')
            ?? DB::table('statuses')->insertGetId([
                'status_api_id' => $car['vehicle_record']['status']['status_api_id'],
                'name' => $car['vehicle_record']['status']['name']
            ]);
            // Log::info('Status', ['data' => $status_id]);

        $title_id = !empty($car['vehicle_record']['title_title'])
            ? DB::table('titles')
                ->where('title_api_id', $car['vehicle_record']['title_title']['title_api_id'])
                ->value('id')
                ?? DB::table('titles')->insertGetId([
                    'title_api_id' => $car['vehicle_record']['title_title']['title_api_id'],
                    'name' => $car['vehicle_record']['title_title']['name']
                ])
            : null;


            // Log::info('Title', ['data' => $title_id]);

        $detailed_title_id = DB::table('detailed_titles')
            ->where('detailed_title_api_id', $car['vehicle_record']['detailed_title']['detailed_title_api_id'])
            ->value('id')
            ?? DB::table('detailed_titles')->insertGetId([
                'detailed_title_api_id' => $car['vehicle_record']['detailed_title']['detailed_title_api_id'],
                'name' => $car['vehicle_record']['detailed_title']['name']
            ]);
            // Log::info('Detailed Title', ['data' => $detailed_title_id]);

        $damage_id = !empty($car['vehicle_record']['damageMain'])
            ? DB::table('damages')
                ->where('damage_api_id', $car['vehicle_record']['damageMain']['damage_api_id'])
                ->value('id')
                ?? DB::table('damages')->insertGetId([
                    'damage_api_id' => $car['vehicle_record']['damageMain']['damage_api_id'],
                    'name' => $car['vehicle_record']['damageMain']['name']
                ])
            : null;
            // Log::info('Damage', ['data' => $damage_id]);

        $damage_second = !empty($car['vehicle_record']['damageSecond'])
            ? DB::table('damages')
                ->where('damage_api_id', $car['vehicle_record']['damageSecond']['damage_api_id'])
                ->value('id')
                ?? DB::table('damages')->insertGetId([
                    'damage_api_id' => $car['vehicle_record']['damageSecond']['damage_api_id'],
                    'name' => $car['vehicle_record']['damageSecond']['name']
                ])
            : null;
            // Log::info('Damage Second', ['data' => $damage_second]);

        $country_id = DB::table('countries')
            ->where('iso', $car['vehicle_record']['country']['iso'])
            ->value('id')
            ?? DB::table('countries')->insertGetId([
                'iso' => $car['vehicle_record']['country']['iso'],
                'name' => $car['vehicle_record']['country']['name']
            ]);
            // Log::info('Country', ['data' => $country_id]);

        $state_id = !empty($car['vehicle_record']['state'])
            ? DB::table('states')
                ->where('state_api_id', $car['vehicle_record']['state']['state_api_id'])
                ->value('id')
                ?? DB::table('states')->insertGetId([
                    'state_api_id' => $car['vehicle_record']['state']['state_api_id'],
                    'country_id' => $country_id,
                    'code' => $car['vehicle_record']['state']['code'],
                    'name' => $car['vehicle_record']['state']['name']
                ])
            : null;
            // Log::info('State', ['data' => $state_id]);

        $city_id = !empty($car['vehicle_record']['city'])
            ? DB::table('cities')
                ->where('city_api_id', $car['vehicle_record']['city']['city_api_id'])
                ->value('id')
                ?? DB::table('cities')->insertGetId([
                    'city_api_id' => $car['vehicle_record']['city']['city_api_id'],
                    'state_id' => $state_id,
                    'name' => $car['vehicle_record']['city']['name']
                ])
            : null;
            // Log::info('City', ['data' => $city_id]);

        $location_id = !empty($car['vehicle_record']['locationRecord']['location_api_id'])
            ? DB::table('locations')
                ->where('location_api_id', $car['vehicle_record']['locationRecord']['location_api_id'])
                ->value('id')
                ?? DB::table('locations')->insertGetId([
                    'location_api_id' => $car['vehicle_record']['locationRecord']['location_api_id'],
                    'city_id' => $city_id,
                    'name' => trim($car['vehicle_record']['locationRecord']['name']) ?: 'Unnamed Location',
                    'latitude' => $car['vehicle_record']['locationRecord']['latitude'] ?? null,
                    'longitude' => $car['vehicle_record']['locationRecord']['longitude'] ?? null,
                    'postal_code' => trim($car['vehicle_record']['locationRecord']['postal_code']) ?: null,
                    'is_offsite' => $car['vehicle_record']['locationRecord']['is_offsite'] ?? false,
                    'raw' => $car['vehicle_record']['locationRecord']['raw'] ?? '{}'
                ])
            : null;
            // Log::info('Location', ['data' => $location_id]);

        $data = [
            'manufacturer_id' => $manufacturer_id,
            'vehicle_model_id' =>  $model_id,
            'generation_id' => $generation_id,
            'body_type_id' => $body_type_id,
            'color_id' => $color_id,
            'engine_id' => $engine_id,
            'transmission_id' => $transmission_id,
            'drive_wheel_id' =>  $drive_wheel_id,
            'vehicle_type_id' => $vehicle_type_id,
            'fuel_id' => $fuel_id,
            'api_id' => $car['vehicle_record']['api_id'] ?? null,
            'year' => $car['vehicle_record']['year'] ?? null,
            'year_id' => $year,
            'title' => $car['vehicle_record']['title'] ?? null,
            'vin' => $car['vehicle_record']['vin'] ?? null,
            'cylinders' => $car['vehicle_record']['cylinders'] ?? null,
            // Lot Data Processing
            'salvage_id' => $car['vehicle_record']['salvage_id'] ?? null,
            'lot_id' => $car['vehicle_record']['lot_id'] ?? null,
            'domain_id' =>  $domain_id,
            'selling_branch' => $selling_branch_id,
            'external_id' => $car['vehicle_record']['external_id'] ?? null,
            'odometer_km' => $car['vehicle_record']['odometer_km'] ?? null,
            'odometer_mi' => $car['vehicle_record']['odometer_mi'] ?? null,
            'odometer_status' => $car['vehicle_record']['odometer_status'] ?? null,
            'estimate_repair_price' => $car['vehicle_record']['estimate_repair_price'] ?? null,
            'pre_accident_price' => $car['vehicle_record']['pre_accident_price'] ?? null,
            'clean_wholesale_price' => $car['vehicle_record']['clean_wholesale_price'] ?? null,
            'actual_cash_value' => $car['vehicle_record']['actual_cash_value'] ?? null,
            'sale_date' => $car['vehicle_record']['sale_date'] ?? null,
            'sale_date_updated_at' => $car['vehicle_record']['sale_date_updated_at'] ?? null,
            'bid' => $car['vehicle_record']['bid'] ?? null,
            'bid_updated_at' => $car['vehicle_record']['bid_updated_at'] ?? null,
            'buy_now' => $car['vehicle_record']['buy_now'] ?? null,
            'buy_now_updated_at' => $car['vehicle_record']['buy_now_updated_at'] ?? null,
            'final_bid' => $car['vehicle_record']['final_bid'] ?? null,
            'final_bid_updated_at' => $car['vehicle_record']['final_bid_updated_at'] ?? null,
            'keys_available' => $car['vehicle_record']['keys_available'] ?? null,
            'airbags' => $car['vehicle_record']['airbags'] ?? null,
            'grade_iaai' => $car['vehicle_record']['grade_iaai'] ?? null,
            'odometer_id' => $odometer_id,
            'seller_id' => $seller_id,
            'seller_type_id' => $seller_type_id,
            'condition_id' => $condition_id,
            'status_id' => $status_id,
            'title_id' => $title_id,
            'detailed_title_id' => $detailed_title_id,
            'damage_id' => $damage_id,
            'damage_main' => $damage_id,
            'damage_second' => $damage_second,
            'buy_now_id' => $car['vehicle_record']['buy_now_db'] ?? 2,
            'details' => $car['vehicle_record']['details'] ?? null,
            'location_id' => $location_id,
            'image_id' => $imageId,
        ];

        Log::info('Lot ID', ['lot_id' => $car['vehicle_record']['lot_id'] ?? null, 'vin' => $car['vehicle_record']['vin'] ?? null]);
        // Log::info('Returned Array Data', ['data' => json_encode($data)]);

        if (preg_match('/[A-Za-z]/', $car['vehicle_record']['lot_id'])) {
            // Skip this record if it contains any letters
            return null;
        }
        return $data;

    }



}
