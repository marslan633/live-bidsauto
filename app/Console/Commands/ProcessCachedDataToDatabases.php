<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, RemoteCacheKey,
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;

class ProcessCachedDataToDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:process-cached-data-to-databases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process cached data into database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateTime = Carbon::now();
        $this->info("Process started at: " . $startDateTime);
        \Log::info("Process started at: " . $startDateTime);

        DB::beginTransaction();
        try{
            $cronRun = DB::table('cron_run_history')->insertGetId([
                'cron_name' => 'process_cached_data_to_database',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Lock the cache keys for update
            $cacheKeys = RemoteCacheKey::where('cache_key', 'like', 'vehicle_data%')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            // ->take(10)
            ->get();

            if ($cacheKeys->isEmpty()) {
                $this->info("No pending cache keys found.");
                DB::commit();
                return;
            }

            $cacheKeyIds = $cacheKeys->pluck('id')->toArray();
              // Update status in bulk
            RemoteCacheKey::whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
            DB::commit();
        }catch(\Exception $e){
            DB::rollBack();
            $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            return;
        }

         // **Batch processing setup**
        $batchData = [];
        $batchSize = 1000; // Process in chunks of 1000
        foreach ($cacheKeys as $cacheKey) {
            try {
                $key = $cacheKey->cache_key;
                $data = json_decode(Cache::store('redis')->get($key), true);
                if (!$data) {
                    $this->info("No data found for key: {$key}");
                    RemoteCacheKey::where('cache_key', $key)->delete();
                    continue;
                }
                foreach ($data as $car) {
                    // **Process Data but Store in Batch**
                    $batchData[] = $this->prepareCarData((array)$car);

                    // If batch reaches 1000, insert and reset
                    if (count($batchData) >= $batchSize) {
                        $this->info("Start Processing Bactehd Data For Redis");
                        $this->insertBatch($batchData);
                        $batchData = []; // Reset batch
                        $this->info("Reset Batch Data");
                    }

                }

                // Remove cache key from DB and Redis
                RemoteCacheKey::where('cache_key', $key)->delete();
                Cache::store('redis')->forget($key);

            } catch (\Exception $e) {
                \Log::error("Error processing key {$key}: " . $e->getMessage());
                $this->info("Data Process Error For Removed Redis:");
                // $cacheKey->update(['status' => 'pending']); // Revert status
            }
        }

         // Insert any remaining data (if less than 1000)
        if (!empty($batchData)) {
            $this->insertBatch($batchData);
        }

        DB::table('cron_run_history')->where('id', $cronRun)->update([
            'end_time' => Carbon::now(),
            'status' => 'success',
            'updated_at' => now(),
        ]);


    }


    private function prepareCarData(array $car)
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
            'selling_branch' => $car['vehicle_record']['selling_branch'] ? SellingBranch::firstOrCreate(
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
            'title_id' => Title::firstOrCreate(
                ['title_api_id' => $car['vehicle_record']['title_title']['title_api_id']],
                ['name' => $car['vehicle_record']['title_title']['name']]
            )->id,
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
            'buy_now_id' => BuyNow::where('name', $car['vehicle_record']['buy_now'])->value('id'),
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

        // Check if the record was newly created
        if ($vehicleRecord->wasRecentlyCreated) {
            $vehicleRecord->update([
                'processed_at' => Carbon::now(),
                'is_new' => true,
            ]);
        } elseif ($vehicleRecord->wasChanged()) {
            // Updated record
            if ($vehicleRecord->is_new) {
                $vehicleRecord->update([
                    'processed_at' => Carbon::now(),
                ]);
            }
        }

    }




    /**
     * ✅ Insert batch of processed data
     */
    private function insertBatch(array $batchData)
    {
        if (empty($batchData)) {
            return;
        }

        // Extract API IDs from batchData
        $apiIds = array_column($batchData, 'api_id');

        // Fetch existing records by API ID
        $existingRecords = VehicleRecord::whereIn('api_id', $apiIds)->pluck('id', 'api_id');

        // Lists for new and updated records
        $newRecords = [];
        $updatedRecords = [];

        foreach ($batchData as $record) {
            if (isset($existingRecords[$record['api_id']])) {
                // Existing record - update full data
                $record['id'] = $existingRecords[$record['api_id']]; // Add ID for update
                $record['processed_at'] = Carbon::now();
                $updatedRecords[] = $record;
            } else {
                // New record - insert
                $record['is_new'] = true;
                $record['processed_at'] = Carbon::now();
                $newRecords[] = $record;
            }
        }

        // ✅ Bulk Insert New Records
        if (!empty($newRecords)) {
            DB::table('vehicle_records')->insert($newRecords);
            $this->info("Inserted " . count($newRecords) . " new records.");
        }

        // ✅ Bulk Update Existing Records (Full Data Update)
        if (!empty($updatedRecords)) {
            // Convert data for bulk update
            $updateQuery = "UPDATE vehicle_records SET ";
            $columns = array_keys($updatedRecords[0]);
            $updateFields = [];
            foreach ($columns as $column) {
                if ($column !== 'id') {
                    $updateFields[] = "`$column` = VALUES(`$column`)";
                }
            }
            $updateQuery .= implode(", ", $updateFields) . " WHERE id = VALUES(id)";

            DB::table('vehicle_records')->upsert($updatedRecords, ['id'], $columns);
            $this->info("Updated " . count($updatedRecords) . " existing records.");
        }
    }




    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        \Log::error($errorMessage);
        DB::table('cron_run_history')->where('id', $cronRun)->update([
            'end_time' => Carbon::now(),
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
    }
}
