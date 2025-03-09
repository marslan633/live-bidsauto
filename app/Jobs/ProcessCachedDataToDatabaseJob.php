<?php

namespace App\Jobs;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, RemoteCacheKey,
};
use Carbon\Carbon;
// use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessCachedDataToDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $cacheKeyId;
    protected $cacheKey;
    // protected $queue = 'process_cached_data_to_database_job';

    /**
     * Create a new job instance.
     */
    public function __construct($cacheKeyId, $cacheKey)
    {
        $this->queue = 'process_cached_data_to_database_job';
        $this->cacheKeyId = $cacheKeyId;
        $this->cacheKey = $cacheKey;
        Log::info('Log From Database Constructor');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Log::info('Log From Database Hanlde');
          try {
                $key = $this->cacheKey;
                $data = json_decode(Cache::store('redis_cache')->get($key), true);
                if (!$data) {
                    Log::warning("No data found for key: {$key}");
                    return;
                }
                $batchData = [];
                $batchSize = intval(config('app.batch_size'));
                foreach ($data as $car) {
                    // Log::info('Starting Batch Insert');
                    // **Process Data but Store in Batch**
                    $batchData[] = $this->prepareCarData((array) $car);
                    Log::info('Batch Condiiton', ['batchData' => count($batchData), 'batchSize' => $batchSize]);
                    // If batch reaches 1000, insert and reset
                    if (count($batchData) >= $batchSize) {
                        Log::info('Batch Inserted');
                        $this->insertBatch($batchData);
                        $batchData = []; // Reset batch
                    }else{
                        Log::info('Batch Condition Not Meet');
                    }

                }

                if (!empty($batchData)) {
                    $this->insertBatch($batchData);
                }


            } catch (\Exception $e) {
                Log::error("Error processing key {$key}: " . $e->getMessage());
            }
    }

    function getOrInsert($table, $where, $data)
    {
        return DB::connection('mysql')->table($table)
            ->where($where)
            ->value('id')
            ?? DB::connection('mysql')->table($table)->insertGetId(array_merge($where, $data));
    }

    public function prepareCarData(array $car)
    {
        // Log::info('Car Dara', ['CarData' => json_encode($car)]);
        $year = null;
        if (!isset($car['year'])) {
            $year = DB::connection('mysql')->table('years')->insertGetId(['name' => $car['year']]);
        }
        Log::info('Year', ['data' => $year]);

        $car['vehicle_record'] = (array) $car['vehicle_record'];
        $model_id = DB::connection('mysql')->table('vehicle_models')
                ->where('vehicle_model_api_id', $car['model']['vehicle_model_api_id'])
                ->value('id') // Fetch only the 'id' column for efficiency
                ?? DB::connection('mysql')->table('vehicle_models')->insertGetId([
                    'vehicle_model_api_id' => $car['model']['vehicle_model_api_id'],
                    'name' => $car['model']['name'],
                ]);
        Log::info('Model', ['data' => $model_id]);


        $imageRecord = $car['vehicle_record']['imageRecord'] ?? [];

        if (!isset($imageRecord['image_api_id'])) {
            $imageId = null;
        } else {
            $imageId = DB::transaction(function () use ($imageRecord) {
                $existingImage = DB::connection('mysql')->table('images')
                    ->where('image_api_id', $imageRecord['image_api_id'])
                    ->first(['id']);

                if ($existingImage) {
                    DB::connection('mysql')->table('images')
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

                return DB::connection('mysql')->table('images')->insertGetId([
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
        Log::info('Image', ['data' => $imageId]);


        $manufacturer_id =  DB::connection('mysql')->table('manufacturers')
                ->where('manufacturer_api_id', $car['manufacturer']['manufacturer_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('manufacturers')->insertGetId([
                    'manufacturer_api_id' => $car['manufacturer']['manufacturer_api_id'],
                    'name' => $car['manufacturer']['name'],
                ]);
        Log::info('Manufacturer', ['data' => $manufacturer_id]);

        $generation_id = DB::connection('mysql')->table('generations')
        ->where('generation_api_id', $car['generation']['generation_api_id'])
        ->value('id') ?? DB::connection('mysql')->table('generations')->insertGetId([
                'generation_api_id' => $car['generation']['generation_api_id'],
                'name' => $car['generation']['name'],
                'model_id' => $model_id,
            ]);

        Log::info('Generation', ['data' => $generation_id]);

        $body_type_id = DB::connection('mysql')->table('body_types')
                ->where('body_type_api_id', $car['body_type']['body_type_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('body_types')->insertGetId([
                    'body_type_api_id' => $car['body_type']['body_type_api_id'],
                    'name' => $car['body_type']['name'],
                ]);

        Log::info('Body Type', ['data' => $body_type_id]);

        $color_id =  DB::connection('mysql')->table('colors')
                ->where('color_api_id', $car['color']['color_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('colors')->insertGetId([
                    'color_api_id' => $car['color']['color_api_id'],
                    'name' => $car['color']['name'],
                ]);
        Log::info('Color', ['data' => $color_id]);
        $engine_id =  DB::connection('mysql')->table('engines')
                ->where('engine_api_id', $car['engine']['engine_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('engines')->insertGetId([
                    'engine_api_id' => $car['engine']['engine_api_id'],
                    'name' => $car['engine']['name'],
                ]);
                Log::info('Engine', ['data' => $engine_id]);

        $transmission_id =  DB::connection('mysql')->table('transmissions')
                ->where('transmission_api_id', $car['transmission']['transmission_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('transmissions')->insertGetId([
                    'transmission_api_id' => $car['transmission']['transmission_api_id'],
                    'name' => $car['transmission']['name'],
                ]);
                Log::info('Transmission', ['data' => $transmission_id]);

        $drive_wheel_id =  DB::connection('mysql')->table('drive_wheels')
                ->where('drive_wheel_api_id', $car['drive_wheel']['drive_wheel_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('drive_wheels')->insertGetId([
                    'drive_wheel_api_id' => $car['drive_wheel']['drive_wheel_api_id'],
                    'name' => $car['drive_wheel']['name'],
                ]);
                Log::info('Driver Wheel', ['data' => $drive_wheel_id]);

        $vehicle_type_id = DB::connection('mysql')->table('vehicle_types')
                ->where('vehicle_type_api_id', $car['vehicle_type']['vehicle_type_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('vehicle_types')->insertGetId([
                    'vehicle_type_api_id' => $car['vehicle_type']['vehicle_type_api_id'],
                    'name' => $car['vehicle_type']['name'],
                ]);
                Log::info('Vehicle Type', ['data' => $vehicle_type_id]);

        $fuel_id =  DB::connection('mysql')->table('fuels')
                ->where('fuel_api_id', $car['fuel']['fuel_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('fuels')->insertGetId([
                    'fuel_api_id' => $car['fuel']['fuel_api_id'],
                    'name' => $car['fuel']['name'],
                ]);
                Log::info('Fuel', ['data' => $fuel_id]);

        $domain_id = isset($car['vehicle_record']['domain'])
        ?  DB::connection('mysql')->table('domains')
                ->where('domain_api_id', $car['vehicle_record']['domain']['domain_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('domains')->insertGetId([
                    'domain_api_id' => $car['vehicle_record']['domain']['domain_api_id'],
                    'name' => $car['vehicle_record']['domain']['name'],
                ])
        : null;
                Log::info('Domain', ['data' => $domain_id]);

        $selling_branch_id = isset($car['vehicle_record']['selling_branch'])
        ?  DB::connection('mysql')->table('selling_branches')
                ->where('selling_branch_api_id', $car['vehicle_record']['selling_branch']['selling_branch_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('selling_branches')->insertGetId([
                    'selling_branch_api_id' => $car['vehicle_record']['selling_branch']['selling_branch_api_id'],
                    'name' => $car['vehicle_record']['selling_branch']['name'],
                    'link' => $car['vehicle_record']['selling_branch']['link'],
                    'number' => $car['vehicle_record']['selling_branch']['number'],
                    'domain_id' => $domain_id, // Use the computed domain_id
                ])
        : null;
        Log::info('Seller Branch', ['data' => $selling_branch_id]);

        $odometer_id = DB::connection('mysql')->table('odometers')
            ->where('name', $car['vehicle_record']['odometer']['name'])
            ->value('id')
            ?? DB::connection('mysql')->table('odometers')->insertGetId(['name' => $car['vehicle_record']['odometer']['name']]);
        Log::info('Odometer', ['data' => $odometer_id]);

        $seller_id = DB::connection('mysql')->table('sellers')
            ->where('seller_api_id', $car['vehicle_record']['seller']['seller_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('sellers')->insertGetId([
                'seller_api_id' => $car['vehicle_record']['seller']['seller_api_id'],
                'name' => $car['vehicle_record']['seller']['name']
            ]);
            Log::info('Seller', ['data' => $seller_id]);

        $seller_type_id = DB::connection('mysql')->table('seller_types')
            ->where('seller_type_api_id', $car['vehicle_record']['seller_type']['seller_type_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('seller_types')->insertGetId([
                'seller_type_api_id' => $car['vehicle_record']['seller_type']['seller_type_api_id'],
                'name' => $car['vehicle_record']['seller_type']['name']
            ]);
            Log::info('Seller Type', ['data' => $seller_type_id]);

        $condition_id = DB::connection('mysql')->table('conditions')
            ->where('condition_api_id', $car['vehicle_record']['condition']['condition_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('conditions')->insertGetId([
                'condition_api_id' => $car['vehicle_record']['condition']['condition_api_id'],
                'name' => $car['vehicle_record']['condition']['name']
            ]);
            Log::info('Condition', ['data' => $condition_id]);

        $status_id = DB::connection('mysql')->table('statuses')
            ->where('status_api_id', $car['vehicle_record']['status']['status_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('statuses')->insertGetId([
                'status_api_id' => $car['vehicle_record']['status']['status_api_id'],
                'name' => $car['vehicle_record']['status']['name']
            ]);
            Log::info('Status', ['data' => $status_id]);

        $title_id = !empty($car['vehicle_record']['title_title'])
            ? DB::connection('mysql')->table('titles')
                ->where('title_api_id', $car['vehicle_record']['title_title']['title_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('titles')->insertGetId([
                    'title_api_id' => $car['vehicle_record']['title_title']['title_api_id'],
                    'name' => $car['vehicle_record']['title_title']['name']
                ])
            : null;
            Log::info('Title', ['data' => $title_id]);

        $detailed_title_id = DB::connection('mysql')->table('detailed_titles')
            ->where('detailed_title_api_id', $car['vehicle_record']['detailed_title']['detailed_title_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('detailed_titles')->insertGetId([
                'detailed_title_api_id' => $car['vehicle_record']['detailed_title']['detailed_title_api_id'],
                'name' => $car['vehicle_record']['detailed_title']['name']
            ]);
            Log::info('Detailed Title', ['data' => $detailed_title_id]);

        $damage_id = !empty($car['vehicle_record']['damageMain'])
            ? DB::connection('mysql')->table('damages')
                ->where('damage_api_id', $car['vehicle_record']['damageMain']['damage_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('damages')->insertGetId([
                    'damage_api_id' => $car['vehicle_record']['damageMain']['damage_api_id'],
                    'name' => $car['vehicle_record']['damageMain']['name']
                ])
            : null;
            Log::info('Damage', ['data' => $damage_id]);

        $damage_second = !empty($car['vehicle_record']['damageSecond'])
            ? DB::connection('mysql')->table('damages')
                ->where('damage_api_id', $car['vehicle_record']['damageSecond']['damage_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('damages')->insertGetId([
                    'damage_api_id' => $car['vehicle_record']['damageSecond']['damage_api_id'],
                    'name' => $car['vehicle_record']['damageSecond']['name']
                ])
            : null;
            Log::info('Damage Second', ['data' => $damage_second]);

        $country_id = DB::connection('mysql')->table('countries')
            ->where('iso', $car['vehicle_record']['country']['iso'])
            ->value('id')
            ?? DB::connection('mysql')->table('countries')->insertGetId([
                'iso' => $car['vehicle_record']['country']['iso'],
                'name' => $car['vehicle_record']['country']['name']
            ]);
            Log::info('Country', ['data' => $country_id]);

        $state_id = !empty($car['vehicle_record']['state'])
            ? DB::connection('mysql')->table('states')
                ->where('state_api_id', $car['vehicle_record']['state']['state_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('states')->insertGetId([
                    'state_api_id' => $car['vehicle_record']['state']['state_api_id'],
                    'country_id' => $country_id,
                    'code' => $car['vehicle_record']['state']['code'],
                    'name' => $car['vehicle_record']['state']['name']
                ])
            : null;
            Log::info('State', ['data' => $state_id]);

        $city_id = !empty($car['vehicle_record']['city'])
            ? DB::connection('mysql')->table('cities')
                ->where('city_api_id', $car['vehicle_record']['city']['city_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('cities')->insertGetId([
                    'city_api_id' => $car['vehicle_record']['city']['city_api_id'],
                    'state_id' => $state_id,
                    'name' => $car['vehicle_record']['city']['name']
                ])
            : null;
            Log::info('City', ['data' => $city_id]);

        $location_id = !empty($car['vehicle_record']['locationRecord']['location_api_id'])
            ? DB::connection('mysql')->table('locations')
                ->where('location_api_id', $car['vehicle_record']['locationRecord']['location_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('locations')->insertGetId([
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
            Log::info('Location', ['data' => $location_id]);

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
            'buy_now_id' => DB::connection('mysql')->table('buy_nows')->where('name', $car['vehicle_record']['buy_now'])->value('id') ?? null,
            'details' => $car['vehicle_record']['details'] ?? null,
            'location_id' => $location_id,
            'image_id' => $imageId,
        ];
        // Log::info('Returned Array Data', ['data' => json_encode($data)]);
        return $data;

    }


      /**
     * ✅ Insert batch of processed data
     */
    public function insertBatch(array $batchData)
    {
        Log::info('Starting Batch Insertion');
        try {
            if (empty($batchData)) {
                return;
            }

            DB::connection('mysql')->beginTransaction(); // ✅ Start Transaction
            DB::connection('mysql_remote')->beginTransaction(); // ✅ Start Transaction

            // Extract API IDs from batchData
            $apiIds = array_column($batchData, 'api_id');

            // Fetch existing records by API ID
            $existingRecords = DB::connection('mysql')->table('vehicle_records')->whereIn('api_id', $apiIds)->pluck('id', 'api_id');

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
                        $newRecords[] = $record;
                    }
                } catch (\Exception $e) {
                    $failedRecords[] = $record;
                    Log::info("Skipping record due to error: " . $e->getMessage());
                }
            }

            // ✅ Bulk Insert New Records
            if (!empty($newRecords)) {
                DB::connection('mysql')->table('vehicle_records')->insert($newRecords);
            }

            // ✅ Bulk Update Existing Records
            if (!empty($updatedRecords)) {
                DB::connection('mysql')->table('vehicle_records')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
            }

            DB::connection('mysql')->commit(); // ✅ Commit Successful Inserts
            DB::connection('mysql_remote')->table('cache_keys')->where('id', $this->cacheKeyId)->delete();

            DB::connection('mysql_remote')->commit(); // ✅ Commit Successful Inserts

            Cache::store('redis_cache')->forget($this->cacheKey);


        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack(); // ❌ Rollback only in case of a major failure
            DB::connection('mysql_remote')->rollBack(); // ❌ Rollback only in case of a major failure

            // Mark cache as pending in case of failure
            DB::connection('mysql_remote')->table('cache_keys')->where('id', $this->cacheKeyId)->update(['status' => 'pending']);

            Log::info("Batch insert failed: " . $e->getMessage());
        }
    }


    // public function prepareCarDataOld(array $car)
    // {
    //     $year = null;
    //     if (!empty($car['year'])) {
    //         $year = DB::connection('mysql')->table('years')->firstOrCreate(['name' => $car['year']])->id;
    //     }
    //     $car['vehicle_record'] = (array) $car['vehicle_record'];
    //     $model_id = DB::connection('mysql')->table('vehicle_models')->firstOrCreate(['vehicle_model_api_id' => $car['model']['vehicle_model_api_id']], ['name' => $car['model']['name']])->id;

    //     $imageRecord = $car['vehicle_record']['imageRecord'] ?? [];
    //     if (!isset($imageRecord['image_api_id'])) {
    //         $imageId = null;
    //     } else {
    //         $imageId = DB::connection('mysql')->table('images')->updateOrCreate(
    //             ['image_api_id' => $imageRecord['image_api_id']],
    //             [
    //                 'small' => $imageRecord['small'] ?? [],
    //                 'normal' => $imageRecord['normal'] ?? [],
    //                 'big' => $imageRecord['big'] ?? [],
    //                 'downloaded' => $imageRecord['downloaded'] ?? [],
    //                 'exterior' => $imageRecord['exterior'] ?? [],
    //                 'interior' => $imageRecord['interior'] ?? [],
    //                 'video' => $imageRecord['video'] ?? null,
    //                 'video_youtube_id' => $imageRecord['video_youtube_id'] ?? null,
    //                 'external_panorama_url' => $imageRecord['external_panorama_url'] ?? null,
    //             ]
    //         )->id;
    //     }

    //     return [
    //         'manufacturer_id' => DB::connection('mysql')->table('manufacturers')->firstOrCreate(['manufacturer_api_id' => $car['manufacturer']['manufacturer_api_id']], ['name' => $car['manufacturer']['name']])->id,
    //         'vehicle_model_id' =>  $model_id,
    //         'generation_id' => DB::connection('mysql')->table('generations')->firstOrCreate(['generation_api_id' => $car['generation']['generation_api_id']], ['name' => $car['generation']['name'], 'model_id' =>  $model_id])->id,
    //         'body_type_id' => DB::connection('mysql')->table('body_types')->firstOrCreate(['body_type_api_id' => $car['body_type']['body_type_api_id']], ['name' => $car['body_type']['name']])->id,
    //         'color_id' => DB::connection('mysql')->table('colors')->firstOrCreate(['color_api_id' => $car['color']['color_api_id']], ['name' => $car['color']['name']])->id,
    //         'engine_id' => DB::connection('mysql')->table('engines')->firstOrCreate(['engine_api_id' => $car['engine']['engine_api_id']], ['name' => $car['engine']['name']])->id,
    //         'transmission_id' => DB::connection('mysql')->table('transmissions')->firstOrCreate(['transmission_api_id' => $car['transmission']['transmission_api_id']], ['name' => $car['transmission']['name']])->id,
    //         'drive_wheel_id' =>  DB::connection('mysql')->table('drive_wheels')->firstOrCreate(['drive_wheel_api_id' => $car['drive_wheel']['drive_wheel_api_id']],['name' => $car['drive_wheel']['name']])->id,
    //         'vehicle_type_id' => DB::connection('mysql')->table('vehicle_types')->firstOrCreate(['vehicle_type_api_id' => $car['vehicle_type']['vehicle_type_api_id']],['name' => $car['vehicle_type']['name']])->id,
    //         'fuel_id' => DB::connection('mysql')->table('fuels')->firstOrCreate(['fuel_api_id' => $car['fuel']['fuel_api_id']], ['name' => $car['fuel']['name']])->id,
    //         'api_id' => $car['vehicle_record']['api_id'] ?? null,
    //         'year' => $car['vehicle_record']['year'] ?? null,
    //         'year_id' => $year,
    //         'title' => $car['vehicle_record']['title'] ?? null,
    //         'vin' => $car['vehicle_record']['vin'] ?? null,
    //         'cylinders' => $car['vehicle_record']['cylinders'] ?? null,
    //         // Lot Data Processing
    //         'salvage_id' => $car['vehicle_record']['salvage_id'] ?? null,
    //         'lot_id' => $car['vehicle_record']['lot_id'] ?? null,
    //         'domain_id' =>  isset($car['vehicle_record']['domain'])
    //         ? DB::connection('mysql')->table('domains')->firstOrCreate(
    //             ['domain_api_id' => $car['vehicle_record']['domain']['domain_api_id']],
    //             ['name' => $car['vehicle_record']['domain']['name']]
    //         )->id
    //         : null,
    //         'selling_branch' => isset($car['vehicle_record']['selling_branch']) ? DB::connection('mysql')->table('selling_branches')->firstOrCreate(
    //             ['selling_branch_api_id' => $car['vehicle_record']['selling_branch']['selling_branch_api_id']],
    //             [
    //                 'name' => $car['vehicle_record']['selling_branch']['name'],
    //                 'link' => $car['vehicle_record']['selling_branch']['link'],
    //                 'number' => $car['vehicle_record']['selling_branch']['number'],
    //                 'domain_id' => $car['vehicle_record']['selling_branch']['domain_id'],
    //             ]
    //         )->id : null,
    //         'external_id' => $car['vehicle_record']['external_id'] ?? null,
    //         'odometer_km' => $car['vehicle_record']['odometer_km'] ?? null,
    //         'odometer_mi' => $car['vehicle_record']['odometer_mi'] ?? null,
    //         'odometer_status' => $car['vehicle_record']['odometer_status'] ?? null,
    //         'estimate_repair_price' => $car['vehicle_record']['estimate_repair_price'] ?? null,
    //         'pre_accident_price' => $car['vehicle_record']['pre_accident_price'] ?? null,
    //         'clean_wholesale_price' => $car['vehicle_record']['clean_wholesale_price'] ?? null,
    //         'actual_cash_value' => $car['vehicle_record']['actual_cash_value'] ?? null,
    //         'sale_date' => $car['vehicle_record']['sale_date'] ?? null,
    //         'sale_date_updated_at' => $car['vehicle_record']['sale_date_updated_at'] ?? null,
    //         'bid' => $car['vehicle_record']['bid'] ?? null,
    //         'bid_updated_at' => $car['vehicle_record']['bid_updated_at'] ?? null,
    //         'buy_now' => $car['vehicle_record']['buy_now'] ?? null,
    //         'buy_now_updated_at' => $car['vehicle_record']['buy_now_updated_at'] ?? null,
    //         'final_bid' => $car['vehicle_record']['final_bid'] ?? null,
    //         'final_bid_updated_at' => $car['vehicle_record']['final_bid_updated_at'] ?? null,
    //         'keys_available' => $car['vehicle_record']['keys_available'] ?? null,
    //         'airbags' => $car['vehicle_record']['airbags'] ?? null,
    //         'grade_iaai' => $car['vehicle_record']['grade_iaai'] ?? null,
    //         'odometer_id' => DB::connection('mysql')->table('odometer')->firstOrCreate(
    //             ['name' => $car['vehicle_record']['odometer']['name']]
    //         )->id,
    //         'seller_id' => DB::connection('mysql')->table('sellers')->firstOrCreate(
    //             ['seller_api_id' => $car['vehicle_record']['seller']['seller_api_id']],
    //             ['name' => $car['vehicle_record']['seller']['name']]
    //         )->id,
    //         'seller_type_id' => DB::connection('mysql')->table('seller_types')->firstOrCreate(
    //             ['seller_type_api_id' => $car['vehicle_record']['seller_type']['seller_type_api_id']],
    //             ['name' => $car['vehicle_record']['seller_type']['name']]
    //         )->id,
    //         'condition_id' => DB::connection('mysql')->table('conditions')->firstOrCreate(
    //             ['condition_api_id' => $car['vehicle_record']['condition']['condition_api_id']],
    //             ['name' => $car['vehicle_record']['condition']['name']]
    //         )->id,
    //         'status_id' =>  DB::connection('mysql')->table('statuses')->firstOrCreate(
    //             ['status_api_id' => $car['vehicle_record']['status']['status_api_id']],
    //             ['name' => $car['vehicle_record']['status']['name']]
    //         )->id,
    //         'title_id' => isset($car['vehicle_record']['title_title']) ? DB::connection('mysql')->table('titles')->firstOrCreate(
    //             ['title_api_id' => $car['vehicle_record']['title_title']['title_api_id']],
    //             ['name' => $car['vehicle_record']['title_title']['name']]
    //         )->id : null,
    //         'detailed_title_id' => DB::connection('mysql')->table('detailed_titles')->firstOrCreate(
    //             ['detailed_title_api_id' => $car['vehicle_record']['detailed_title']['detailed_title_api_id']],
    //             ['name' => $car['vehicle_record']['detailed_title']['name']]
    //         )->id,
    //         'damage_id' => $car['vehicle_record']['damageMain'] ? DB::connection('mysql')->table('damages')->firstOrCreate(
    //             ['damage_api_id' => $car['vehicle_record']['damageMain']['damage_api_id']],
    //             ['name' => $car['vehicle_record']['damageMain']['name']]
    //         )->id : null,
    //         'damage_main' => $car['vehicle_record']['damageMain'] ? DB::connection('mysql')->table('damages')->firstOrCreate(
    //             ['damage_api_id' => $car['vehicle_record']['damageMain']['damage_api_id']],
    //             ['name' => $car['vehicle_record']['damageMain']['name']]
    //         )->id : null,
    //         'damage_second' => $car['vehicle_record']['damageSecond'] ? DB::connection('mysql')->table('damages')->firstOrCreate(
    //             ['damage_api_id' => $car['vehicle_record']['damageSecond']['damage_api_id']],
    //             ['name' => $car['vehicle_record']['damageSecond']['name']]
    //         )->id : null,
    //         'buy_now_id' => DB::connection('mysql')->table('buy_nows')->where('name', $car['vehicle_record']['buy_now'])->value('id') ?? null,
    //         'details' => $car['vehicle_record']['details'] ?? null,
    //         'location_id' => !empty($car['vehicle_record']['locationRecord']) && !empty($car['vehicle_record']['locationRecord']['location_api_id']) ? DB::connection('mysql')->table('locations')->firstOrCreate(
    //             ['location_api_id' => $car['vehicle_record']['locationRecord']['location_api_id']],
    //             [
    //                 'city_id' => !empty($car['vehicle_record']['city']) ? DB::connection('mysql')->table('cities')->firstOrCreate(
    //                     ['city_api_id' => $car['vehicle_record']['city']['city_api_id']],
    //                     [
    //                         'state_id' => !empty($car['vehicle_record']['state']) ? DB::connection('mysql')->table('states')->firstOrCreate(
    //                             ['state_api_id' => $car['vehicle_record']['state']['state_api_id']],
    //                             [
    //                                 'country_id' => $car['vehicle_record']['country'] ? DB::connection('mysql')->table('countries')->firstOrCreate(
    //                                     ['iso' => $car['vehicle_record']['country']['iso']],
    //                                     ['name' => $car['vehicle_record']['country']['name']]
    //                                 )->id : null,
    //                                 'code' => $car['vehicle_record']['state']['code'],
    //                                 'name' => $car['vehicle_record']['state']['name']
    //                             ]
    //                         )->id : null,
    //                         'name' => $car['vehicle_record']['city']['name']
    //                     ]
    //                 )->id : null,
    //                 'name' => trim($car['vehicle_record']['locationRecord']['name']) ?: 'Unnamed Location',
    //                 'latitude' => $car['vehicle_record']['locationRecord']['latitude'] ?? null,
    //                 'longitude' => $car['vehicle_record']['locationRecord']['longitude'] ?? null,
    //                 'postal_code' => trim($car['vehicle_record']['locationRecord']['postal_code']) ?: null,
    //                 'is_offsite' => $car['vehicle_record']['locationRecord']['is_offsite'] ?? false,
    //                 'raw' => $car['vehicle_record']['locationRecord']['raw'] ?? '{}'
    //             ]
    //         )->id : null,
    //         'image_id' => $imageId,
    //     ];

    // }




     // public function insertBatch(array $batchData)
    // {
    //     try{
    //         if (empty($batchData)) {
    //             return;
    //         }

    //         DB::beginTransaction(); // ✅ Start Transaction

    //         // Extract API IDs from batchData
    //         $apiIds = array_column($batchData, 'api_id');

    //         // Fetch existing records by API ID
    //         $existingRecords = VehicleRecord::whereIn('api_id', $apiIds)->pluck('id', 'api_id');

    //         // Lists for new and updated records
    //         $newRecords = [];
    //         $updatedRecords = [];

    //         foreach ($batchData as $record) {
    //             if (isset($existingRecords[$record['api_id']])) {
    //                 // Existing record - update full data
    //                 $record['id'] = $existingRecords[$record['api_id']]; // Add ID for update
    //                 $record['processed_at'] = Carbon::now();
    //                 $record['updated_at'] = Carbon::now();
    //                 $updatedRecords[] = $record;
    //             } else {
    //                 // New record - insert
    //                 $record['is_new'] = true;
    //                 $record['processed_at'] = Carbon::now();
    //                 $record['created_at'] = Carbon::now();
    //                 $newRecords[] = $record;
    //             }
    //         }

    //         // ✅ Bulk Insert New Records
    //         if (!empty($newRecords)) {
    //             DB::connection('mysql')->table('vehicle_records')->insert($newRecords);
    //             // $this->info("Inserted " . count($newRecords) . " new records.");
    //         }

    //         // ✅ Bulk Update Existing Records (Full Data Update)
    //         if (!empty($updatedRecords)) {
    //             // Convert data for bulk update
    //             $updateQuery = "UPDATE vehicle_records SET ";
    //             $columns = array_keys($updatedRecords[0]);
    //             $updateFields = [];
    //             foreach ($columns as $column) {
    //                 if ($column !== 'id') {
    //                     $updateFields[] = "`$column` = VALUES(`$column`)";
    //                 }
    //             }
    //             $updateQuery .= implode(", ", $updateFields) . " WHERE id = VALUES(id)";

    //             DB::connection('mysql')->table('vehicle_records')->upsert($updatedRecords, ['id'], $columns);
    //             // $this->info("Updated " . count($updatedRecords) . " existing records.");
    //         }

    //         DB::commit(); // ✅ Commit Transaction if everything is successful

    //         // Remove cache key from DB and Redis
    //         RemoteCacheKey::where('id', $this->cacheKeyId)->delete();
    //         Cache::store('redis')->forget($this->cacheKey);

    //     }catch(\Exception $e){
    //         DB::rollBack(); // ❌ Rollback Transaction if an error occurs

    //         // Mark cache as pending in case of failure
    //         RemoteCacheKey::find($this->cacheKeyId)->update(['status' => 'pending']);

    //         // Optionally log the error
    //         Log::error("Batch insert failed: " . $e->getMessage());
    //     }

    // }
}
