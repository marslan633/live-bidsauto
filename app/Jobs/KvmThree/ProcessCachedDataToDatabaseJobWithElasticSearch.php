<?php

namespace App\Jobs\KvmThree;

use App\Models\BodyType;
use App\Models\City;
use App\Models\Color;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Damage;
use App\Models\DetailedTitle;
use App\Models\Domain;
use App\Models\DriveWheel;
use App\Models\Engine;
use App\Models\Fuel;
use App\Models\Generation;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Odometer;
use App\Models\Seller;
use App\Models\SellerType;
use App\Models\SellingBranch;
use App\Models\State;
use App\Models\Status;
use App\Models\Title;
use App\Models\Transmission;
use App\Models\VehicleModel;
use App\Models\VehicleProcessCachedApiData;
use App\Models\VehicleType;
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
                Log::info('Prepare Data', ['prepareData' => json_encode($preparedData)]);
                if ($preparedData) {
                    $batchData[] = $preparedData;
                }
            }

            if (count($batchData) > 0) {
                $this->insertBatch($batchData, $this->cacheKey->_id);
                $batchData = []; // Reset batch
            } else {
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
        $clientkvmOne = app('ElasticsearchKvmOne');
        try {
            if (empty($batchData)) {
                return;
            }

            // Extract API IDs from batchData
            $lotIds = array_column($batchData, 'lot_id');

            // Fetch existing records by API ID
            $existingRecords = DB::table('vehicle_records')->whereIn('lot_id', $lotIds)->pluck('id', 'lot_id');

            // Lists for new and updated records
            $newRecords = [];
            $updatedRecords = [];
            $failedRecords = []; // ❌ Store records that failed

            foreach ($batchData as $record) {
                try {
                    if (isset($existingRecords[$record['lot_id']])) {
                        // Existing record - update full data
                        $record['id'] = $existingRecords[$record['lot_id']]; // Add ID for update
                        $record['processed_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        // $record['data_source'] = 1;
                        $updatedRecords[] = $record;
                    } else {
                        // New record - insert
                        $record['is_new'] = true;
                        $record['processed_at'] = Carbon::now();
                        $record['created_at'] = Carbon::now();
                        $record['updated_at'] = Carbon::now();
                        $record['data_source'] = 1;
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

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                $VehicleUpdateRecordOne = [];
                $newSaleRecords = [];
                $updatedSaleRecords = [];

                foreach ($updatedRecords as $item) {
                    // DB::table('vehicle_records')->where('id', $item['id'])->update($item);
                    $getVehicleRecord = DB::table('vehicle_records')->where('id', $item['id'])->first();

                    if(!is_null($getVehicleRecord)){
                        $checkRecordSaleDate = Carbon::parse($getVehicleRecord->sale_date);
                        $currentSaleDate = $item['sale_date'];


                        // Condition 1: Sale Date Matched AND data_source = 2 update the record | update or create auction history
                        // Condition 2: Sale Date Not Macthed And Status != 3 And data_source = 2 update or create auction history
                        // Condition 3: Sale Date Not Macthed And Status == 3(sale) Active and Update

                        if($checkRecordSaleDate == $currentSaleDate && $getVehicleRecord->data_source == 2){
                            $dataOne = $item;
                            $dataOne['id'] = $getVehicleRecord->id;
                            $VehicleUpdateRecordOne[] = $dataOne;
                        }elseif($checkRecordSaleDate != $currentSaleDate && $getVehicleRecord->status_id != 3 && $getVehicleRecord->data_source == 2){
                            $dataTwo = $item;
                            $dataTwo['id'] = $getVehicleRecord->id;
                            $VehicleUpdateRecordOne[] = $dataTwo;
                        }elseif($checkRecordSaleDate != $currentSaleDate && $getVehicleRecord->status_id == 3){
                            $dataThree = $item;
                            $dataThree['id'] = $getVehicleRecord->id;
                            $dataThree['data_source'] = 1;
                            $VehicleUpdateRecordOne[] = $dataThree;
                        }


                        if(($checkRecordSaleDate == $currentSaleDate && $getVehicleRecord->data_source == 2)
                        || ($checkRecordSaleDate != $currentSaleDate && $getVehicleRecord->status_id != 3 && $getVehicleRecord->data_source == 2)){
                                $saleAuctionRecord = DB::table('sale_auction_histories')
                                ->where([
                                    'vin' => $item['vin'],
                                    'lot_id' => $item['lot_id'],
                                    'sale_date' => $item['sale_date'],
                                ])->first();
                                $saleData = [
                                    'vin' => strtolower($item['vin']),
                                    'domain_id' => $item['domain_id'],
                                    'sale_date' => $item['sale_date'],
                                    'lot_id' => $item['lot_id'],
                                    'bid' => $item['bid'],
                                    'odometer_mi' => $item['odometer_mi'],
                                    'status_id' => $item['status_id'],
                                    'seller_id' => $item['seller_id'],
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ];
                                if($saleAuctionRecord){
                                    unset($saleData['created_at']);
                                    $updatedSaleData = $saleData;
                                    $updatedSaleData['id'] = $saleAuctionRecord->id;
                                    $updatedSaleRecords[] = $updatedSaleData;
                                }else{
                                    $newSaleRecords[] = $saleData;
                                }
                        }


                    }

                }

                 // ✅ Bulk Insert New Records
                if (!empty($newRecords)) {
                    DB::table('vehicle_records')->insert($newRecords);
                }

                if(!empty($VehicleUpdateRecordOne)){
                    DB::table('vehicle_records')->upsert($VehicleUpdateRecordOne,['id']);
                }
                if(!empty($updatedSaleRecords)){
                    DB::table('sale_auction_histories')->upsert($updatedSaleRecords,['id']);
                }
                if(!empty($newSaleRecords)){
                    DB::table('sale_auction_histories')->insert($newSaleRecords);
                }

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

        try {
            $client = app('ElasticsearchKvmOne');

            $response = $client->exists([
                'index' => 'vehicle_process_cached_api_data',
                'id' => $cacheKey,
            ]);

            if ($response) {
                try {
                    $client->update([
                        'index' => 'vehicle_process_cached_api_data',
                        'id' => $cacheKey,
                        'body' => [
                            'doc' => [
                                'status' => 'completed'
                            ]
                        ]
                    ]);
                } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        $clientkvmOne = app('ElasticsearchKvmOne');
        try{
            // Log::info('Car Dara', ['CarData' => json_encode($car)]);
        $year = null;
        if (isset($car['year'])) {
            $year = DB::table('years')->insertGetId(['name' => $car['year']]);
        }
        // Log::info('Year', ['data' => $year]);

        $car['vehicle_record'] = (array) $car['vehicle_record'];
        $model = VehicleModel::firstOrCreate(
            ['vehicle_model_api_id' => $car['model']['vehicle_model_api_id']],
            ['name' => $car['model']['name']]
        );
        $model_id = $model->id;
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


        $manufacturer = Manufacturer::firstOrCreate(
            ['manufacturer_api_id' => $car['manufacturer']['manufacturer_api_id']],
            ['name' => $car['manufacturer']['name']]
        );
        $manufacturer_id = $manufacturer->id;
        // Log::info('Manufacturer', ['data' => $manufacturer_id]);

        $generation = Generation::firstOrCreate(
            ['generation_api_id' => $car['generation']['generation_api_id']],
            [
                'name' => $car['generation']['name'],
                'model_id' => $model_id
            ]
        );
        $generation_id = $generation->id;

        // Log::info('Generation', ['data' => $generation_id]);

        $bodyType = BodyType::firstOrCreate(
            ['body_type_api_id' => $car['body_type']['body_type_api_id']],
            ['name' => $car['body_type']['name']]
        );
        $body_type_id = $bodyType->id;

        // Log::info('Body Type', ['data' => $body_type_id]);

        $color = Color::firstOrCreate(
            ['color_api_id' => $car['color']['color_api_id']],
            ['name' => $car['color']['name']]
        );
        $color_id = $color->id;

        // Log::info('Color', ['data' => $color_id]);
        $engine = Engine::firstOrCreate(
            ['engine_api_id' => $car['engine']['engine_api_id']],
            ['name' => $car['engine']['name']]
        );

        $engine_id = $engine->id;
        // Log::info('Engine', ['data' => $engine_id]);

        $transmission = Transmission::firstOrCreate(
            ['transmission_api_id' => $car['transmission']['transmission_api_id']],
            ['name' => $car['transmission']['name']]
        );
        $transmission_id = $transmission->id;
        // Log::info('Transmission', ['data' => $transmission_id]);

        $driveWheel = DriveWheel::firstOrCreate(
            ['drive_wheel_api_id' => $car['drive_wheel']['drive_wheel_api_id']],
            ['name' => $car['drive_wheel']['name']]
        );
        $drive_wheel_id = $driveWheel->id;
        // Log::info('Driver Wheel', ['data' => $drive_wheel_id]);

        $vehicleType = VehicleType::firstOrCreate(
            ['vehicle_type_api_id' => $car['vehicle_type']['vehicle_type_api_id']],
            ['name' => $car['vehicle_type']['name']]
        );
        $vehicle_type_id = $vehicleType->id;
        // Log::info('Vehicle Type', ['data' => $vehicle_type_id]);

        $fuel = Fuel::firstOrCreate(
            ['fuel_api_id' => $car['fuel']['fuel_api_id']],
            ['name' => $car['fuel']['name']]
        );
        $fuel_id = $fuel->id;
        // Log::info('Fuel', ['data' => $fuel_id]);

        $domain_id = null;
        if (isset($car['vehicle_record']['domain'])) {
            $domain = Domain::firstOrCreate(
                ['domain_api_id' => $car['vehicle_record']['domain']['domain_api_id']],
                ['name' => $car['vehicle_record']['domain']['name']]
            );
            $domain_id = $domain->id;
        }
        // Log::info('Domain', ['data' => $domain_id]);


        $selling_branch_id = isset($car['vehicle_record']['selling_branch'])
            ? SellingBranch::firstOrCreate(
                ['selling_branch_api_id' => $car['vehicle_record']['selling_branch']['selling_branch_api_id']],
                [
                    'name' => $car['vehicle_record']['selling_branch']['name'],
                    'link' => $car['vehicle_record']['selling_branch']['link'],
                    'number' => $car['vehicle_record']['selling_branch']['number'],
                    'domain_id' => $domain_id,
                ]
            )->id
            : null;
        // Log::info('Seller Branch', ['data' => $selling_branch_id]);

        $odometer_id = Odometer::firstOrCreate(
            ['name' => $car['vehicle_record']['odometer']['name']]
        )->id;
        // Log::info('Odometer', ['data' => $odometer_id]);

        $seller_id = Seller::firstOrCreate(
            ['seller_api_id' => $car['vehicle_record']['seller']['seller_api_id']],
            ['name' => $car['vehicle_record']['seller']['name']]
        )->id;
        // Log::info('Seller', ['data' => $seller_id]);

        $seller_type_id = SellerType::firstOrCreate(
            ['seller_type_api_id' => $car['vehicle_record']['seller_type']['seller_type_api_id']],
            ['name' => $car['vehicle_record']['seller_type']['name']]
        )->id;
        // Log::info('Seller Type', ['data' => $seller_type_id]);

        $condition_id = Condition::firstOrCreate(
            ['condition_api_id' => $car['vehicle_record']['condition']['condition_api_id']],
            ['name' => $car['vehicle_record']['condition']['name']]
        )->id;
        // Log::info('Condition', ['data' => $condition_id]);

        $status_id = Status::firstOrCreate(
            ['status_api_id' => $car['vehicle_record']['status']['status_api_id']],
            ['name' => $car['vehicle_record']['status']['name']]
        )->id;
        // Log::info('Status', ['data' => $status_id]);

        $title_id = !empty($car['vehicle_record']['title_title'])
        ? Title::firstOrCreate(
            ['title_api_id' => $car['vehicle_record']['title_title']['title_api_id']],
            ['name' => $car['vehicle_record']['title_title']['name']]
        )->id
        : null;
        // Log::info('Title', ['data' => $title_id]);

        $detailed_title_id = DetailedTitle::firstOrCreate(
            ['detailed_title_api_id' => $car['vehicle_record']['detailed_title']['detailed_title_api_id']],
            ['name' => $car['vehicle_record']['detailed_title']['name']]
        )->id;
        // Log::info('Detailed Title', ['data' => $detailed_title_id]);

        $damage_id = !empty($car['vehicle_record']['damageMain'])
        ? Damage::firstOrCreate(
            ['damage_api_id' => $car['vehicle_record']['damageMain']['damage_api_id']],
            ['name' => $car['vehicle_record']['damageMain']['name']]
        )->id
        : null;
        // Log::info('Damage', ['data' => $damage_id]);

        $damage_second = !empty($car['vehicle_record']['damageSecond'])
        ? Damage::firstOrCreate(
            ['damage_api_id' => $car['vehicle_record']['damageSecond']['damage_api_id']],
            ['name' => $car['vehicle_record']['damageSecond']['name']]
        )->id
        : null;
        // Log::info('Damage Second', ['data' => $damage_second]);

        $country_id = Country::firstOrCreate(
            ['iso' => $car['vehicle_record']['country']['iso']],
            ['name' => $car['vehicle_record']['country']['name']]
        )->id;
        // Log::info('Country', ['data' => $country_id]);

        $state_id = !empty($car['vehicle_record']['state'])
        ? State::firstOrCreate(
            ['state_api_id' => $car['vehicle_record']['state']['state_api_id']],
            [
                'country_id' => $country_id,
                'code' => $car['vehicle_record']['state']['code'],
                'name' => $car['vehicle_record']['state']['name']
            ]
        )->id
        : null;
        // Log::info('State', ['data' => $state_id]);

        $city_id = !empty($car['vehicle_record']['city'])
        ? City::firstOrCreate(
            ['city_api_id' => $car['vehicle_record']['city']['city_api_id']],
            [
                'state_id' => $state_id,
                'name' => $car['vehicle_record']['city']['name']
            ]
        )->id
        : null;
        // Log::info('City', ['data' => $city_id]);

        $location_id = !empty($car['vehicle_record']['locationRecord']['location_api_id'])
        ? Location::firstOrCreate(
            ['location_api_id' => $car['vehicle_record']['locationRecord']['location_api_id']],
            [
                'city_id' => $city_id,
                'name' => trim($car['vehicle_record']['locationRecord']['name']) ?: 'Unnamed Location',
                'latitude' => $car['vehicle_record']['locationRecord']['latitude'] ?? null,
                'longitude' => $car['vehicle_record']['locationRecord']['longitude'] ?? null,
                'postal_code' => trim($car['vehicle_record']['locationRecord']['postal_code']) ?: null,
                'is_offsite' => $car['vehicle_record']['locationRecord']['is_offsite'] ?? false,
                'raw' => $car['vehicle_record']['locationRecord']['raw'] ?? '{}',
            ]
        )->id
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
            'vin' => strtolower($car['vehicle_record']['vin']) ?? null,
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
        }catch(\Exception $e){
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
}
