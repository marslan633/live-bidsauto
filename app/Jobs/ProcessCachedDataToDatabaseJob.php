<?php

namespace App\Jobs;

use App\Jobs\ProcessCacheKeyJob;
use Illuminate\Bus\Queueable;
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
use Illuminate\Support\Facades\DB;

class ProcessCachedDataToDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $cacheKeyId;
    protected $cacheKey;

     /**
     * Create a new job instance.
     */
    public function __construct($cacheKeyId, $cacheKey)
    {
        $this->cacheKeyId = $cacheKeyId;
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
          try {
                $key = $this->cacheKey;
                $data = json_decode(Cache::store('redis')->get($key), true);
                if (!$data) {
                    Log::info("No data found for key: {$key}");
                    return;
                }
                $batchData = [];
                $batchSize = config('app.batch_size');
                foreach ($data as $car) {
                    Log::info('Starting Batch Insert');
                    // **Process Data but Store in Batch**
                    $batchData[] = $this->prepareCarData((array)$car);

                    // If batch reaches 1000, insert and reset
                    if (count($batchData) >= $batchSize) {
                        Log::info('Batch Inserted');
                        $this->insertBatch($batchData);
                        $batchData = []; // Reset batch
                    }

                }

                if (!empty($batchData)) {
                    $this->insertBatch($batchData);
                }


            } catch (\Exception $e) {
                Log::error("Error processing key {$key}: " . $e->getMessage());
            }
    }

    public function prepareCarData(array $car)
    {
        $year = null;
        if (!empty($car['year'])) {
            $year = Year::firstOrCreate(['name' => $car['year']])->id;
        }
        $car['vehicle_record'] = (array) $car['vehicle_record'];
        $model_id = VehicleModel::firstOrCreate(['vehicle_model_api_id' => $car['model']['vehicle_model_api_id']], ['name' => $car['model']['name']])->id;

        $imageRecord = $car['vehicle_record']['imageRecord'] ?? [];
        if (!isset($imageRecord['image_api_id'])) {
            $imageId = null;
        } else {
            $imageId = Image::updateOrCreate(
                ['image_api_id' => $imageRecord['image_api_id']],
                [
                    'small' => $imageRecord['small'] ?? [],
                    'normal' => $imageRecord['normal'] ?? [],
                    'big' => $imageRecord['big'] ?? [],
                    'downloaded' => $imageRecord['downloaded'] ?? [],
                    'exterior' => $imageRecord['exterior'] ?? [],
                    'interior' => $imageRecord['interior'] ?? [],
                    'video' => $imageRecord['video'] ?? null,
                    'video_youtube_id' => $imageRecord['video_youtube_id'] ?? null,
                    'external_panorama_url' => $imageRecord['external_panorama_url'] ?? null,
                ]
            )->id;
        }

        return [
            'manufacturer_id' => Manufacturer::firstOrCreate(['manufacturer_api_id' => $car['manufacturer']['manufacturer_api_id']], ['name' => $car['manufacturer']['name']])->id,
            'vehicle_model_id' =>  $model_id,
            'generation_id' => Generation::firstOrCreate(['generation_api_id' => $car['generation']['generation_api_id']], ['name' => $car['generation']['name'], 'model_id' =>  $model_id])->id,
            'body_type_id' => BodyType::firstOrCreate(['body_type_api_id' => $car['body_type']['body_type_api_id']], ['name' => $car['body_type']['name']])->id,
            'color_id' => Color::firstOrCreate(['color_api_id' => $car['color']['color_api_id']], ['name' => $car['color']['name']])->id,
            'engine_id' => Engine::firstOrCreate(['engine_api_id' => $car['engine']['engine_api_id']], ['name' => $car['engine']['name']])->id,
            'transmission_id' => Transmission::firstOrCreate(['transmission_api_id' => $car['transmission']['transmission_api_id']], ['name' => $car['transmission']['name']])->id,
            'drive_wheel_id' =>  DriveWheel::firstOrCreate(['drive_wheel_api_id' => $car['drive_wheel']['drive_wheel_api_id']],['name' => $car['drive_wheel']['name']])->id,
            'vehicle_type_id' => VehicleType::firstOrCreate(['vehicle_type_api_id' => $car['vehicle_type']['vehicle_type_api_id']],['name' => $car['vehicle_type']['name']])->id,
            'fuel_id' => Fuel::firstOrCreate(['fuel_api_id' => $car['fuel']['fuel_api_id']], ['name' => $car['fuel']['name']])->id,
            'api_id' => $car['vehicle_record']['api_id'] ?? null,
            'year' => $car['vehicle_record']['year'] ?? null,
            'year_id' => $year,
            'title' => $car['vehicle_record']['title'] ?? null,
            'vin' => $car['vehicle_record']['vin'] ?? null,
            'cylinders' => $car['vehicle_record']['cylinders'] ?? null,
            // Lot Data Processing
            'salvage_id' => $car['vehicle_record']['salvage_id'] ?? null,
            'lot_id' => $car['vehicle_record']['lot_id'] ?? null,
            'domain_id' =>  isset($car['vehicle_record']['domain'])
            ? Domain::firstOrCreate(
                ['domain_api_id' => $car['vehicle_record']['domain']['domain_api_id']],
                ['name' => $car['vehicle_record']['domain']['name']]
            )->id
            : null,
            'selling_branch' => isset($car['vehicle_record']['selling_branch']) ? SellingBranch::firstOrCreate(
                ['selling_branch_api_id' => $car['vehicle_record']['selling_branch']['selling_branch_api_id']],
                [
                    'name' => $car['vehicle_record']['selling_branch']['name'],
                    'link' => $car['vehicle_record']['selling_branch']['link'],
                    'number' => $car['vehicle_record']['selling_branch']['number'],
                    'domain_id' => $car['vehicle_record']['selling_branch']['domain_id'],
                ]
            )->id : null,
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
            'odometer_id' => Odometer::firstOrCreate(
                ['name' => $car['vehicle_record']['odometer']['name']]
            )->id,
            'seller_id' => Seller::firstOrCreate(
                ['seller_api_id' => $car['vehicle_record']['seller']['seller_api_id']],
                ['name' => $car['vehicle_record']['seller']['name']]
            )->id,
            'seller_type_id' => SellerType::firstOrCreate(
                ['seller_type_api_id' => $car['vehicle_record']['seller_type']['seller_type_api_id']],
                ['name' => $car['vehicle_record']['seller_type']['name']]
            )->id,
            'condition_id' => Condition::firstOrCreate(
                ['condition_api_id' => $car['vehicle_record']['condition']['condition_api_id']],
                ['name' => $car['vehicle_record']['condition']['name']]
            )->id,
            'status_id' =>  Status::firstOrCreate(
                ['status_api_id' => $car['vehicle_record']['status']['status_api_id']],
                ['name' => $car['vehicle_record']['status']['name']]
            )->id,
            'title_id' => isset($car['vehicle_record']['title_title']) ? Title::firstOrCreate(
                ['title_api_id' => $car['vehicle_record']['title_title']['title_api_id']],
                ['name' => $car['vehicle_record']['title_title']['name']]
            )->id : null,
            'detailed_title_id' => DetailedTitle::firstOrCreate(
                ['detailed_title_api_id' => $car['vehicle_record']['detailed_title']['detailed_title_api_id']],
                ['name' => $car['vehicle_record']['detailed_title']['name']]
            )->id,
            'damage_id' => $car['vehicle_record']['damageMain'] ? Damage::firstOrCreate(
                ['damage_api_id' => $car['vehicle_record']['damageMain']['damage_api_id']],
                ['name' => $car['vehicle_record']['damageMain']['name']]
            )->id : null,
            'damage_main' => $car['vehicle_record']['damageMain'] ? Damage::firstOrCreate(
                ['damage_api_id' => $car['vehicle_record']['damageMain']['damage_api_id']],
                ['name' => $car['vehicle_record']['damageMain']['name']]
            )->id : null,
            'damage_second' => $car['vehicle_record']['damageSecond'] ? Damage::firstOrCreate(
                ['damage_api_id' => $car['vehicle_record']['damageSecond']['damage_api_id']],
                ['name' => $car['vehicle_record']['damageSecond']['name']]
            )->id : null,
            'buy_now_id' => BuyNow::where('name', $car['vehicle_record']['buy_now'])->value('id') ?? null,
            'details' => $car['vehicle_record']['details'] ?? null,
            'location_id' => !empty($car['vehicle_record']['locationRecord']) && !empty($car['vehicle_record']['locationRecord']['location_api_id']) ? Location::firstOrCreate(
                ['location_api_id' => $car['vehicle_record']['locationRecord']['location_api_id']],
                [
                    'city_id' => !empty($car['vehicle_record']['city']) ? City::firstOrCreate(
                        ['city_api_id' => $car['vehicle_record']['city']['city_api_id']],
                        [
                            'state_id' => !empty($car['vehicle_record']['state']) ? State::firstOrCreate(
                                ['state_api_id' => $car['vehicle_record']['state']['state_api_id']],
                                [
                                    'country_id' => $car['vehicle_record']['country'] ? Country::firstOrCreate(
                                        ['iso' => $car['vehicle_record']['country']['iso']],
                                        ['name' => $car['vehicle_record']['country']['name']]
                                    )->id : null,
                                    'code' => $car['vehicle_record']['state']['code'],
                                    'name' => $car['vehicle_record']['state']['name']
                                ]
                            )->id : null,
                            'name' => $car['vehicle_record']['city']['name']
                        ]
                    )->id : null,
                    'name' => trim($car['vehicle_record']['locationRecord']['name']) ?: 'Unnamed Location',
                    'latitude' => $car['vehicle_record']['locationRecord']['latitude'] ?? null,
                    'longitude' => $car['vehicle_record']['locationRecord']['longitude'] ?? null,
                    'postal_code' => trim($car['vehicle_record']['locationRecord']['postal_code']) ?: null,
                    'is_offsite' => $car['vehicle_record']['locationRecord']['is_offsite'] ?? false,
                    'raw' => $car['vehicle_record']['locationRecord']['raw'] ?? '{}'
                ]
            )->id : null,
            'image_id' => $imageId,
        ];

    }


    /**
     * ✅ Insert batch of processed data
     */
    public function insertBatch(array $batchData)
{
    try {
        if (empty($batchData)) {
            return;
        }

        DB::beginTransaction(); // ✅ Start Transaction

        // Extract API IDs from batchData
        $apiIds = array_column($batchData, 'api_id');

        // Fetch existing records by API ID
        $existingRecords = VehicleRecord::whereIn('api_id', $apiIds)->pluck('id', 'api_id');

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
                Log::error("Skipping record due to error: " . $e->getMessage());
            }
        }

        // ✅ Bulk Insert New Records
        if (!empty($newRecords)) {
            DB::table('vehicle_records')->insert($newRecords);
        }

        // ✅ Bulk Update Existing Records
        if (!empty($updatedRecords)) {
            DB::table('vehicle_records')->upsert($updatedRecords, ['id'], array_keys($updatedRecords[0]));
        }

        DB::commit(); // ✅ Commit Successful Inserts

            RemoteCacheKey::where('id', $this->cacheKeyId)->delete();
            Cache::store('redis')->forget($this->cacheKey);


    } catch (\Exception $e) {
        DB::rollBack(); // ❌ Rollback only in case of a major failure

        // Mark cache as pending in case of failure
        RemoteCacheKey::where('id', $this->cacheKeyId)->update(['status' => 'pending']);

        Log::error("Batch insert failed: " . $e->getMessage());
    }
}

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
    //             DB::table('vehicle_records')->insert($newRecords);
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

    //             DB::table('vehicle_records')->upsert($updatedRecords, ['id'], $columns);
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
