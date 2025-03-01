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
            ->where('status', 'progress')
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
        $batchSize = 1; // Process in chunks of 1000
        foreach ($cacheKeys as $cacheKey) {
            try {
                $key = $cacheKey->cache_key;
                $data = Cache::store('redis')->get($key);
                \Log::info('Without Decode', ['data' => $data]);
                \Log::info('With Decode', ['data' => json_decode($data)]);
                if (!$data) {
                    $this->info("No data found for key: {$key}");
                    RemoteCacheKey::where('cache_key', $key)->delete();
                    continue;
                }
                foreach ($data['data'] as $car) {
                    // **Process Data but Store in Batch**
                    $batchData[] = $this->prepareCarData($car);

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

        return [
            'manufacturer_id' => Manufacturer::firstOrCreate(['manufacturer_api_id' => $car['manufacturer']['manufacturer_api_id']], ['name' => $car['manufacturer']['name']])->id,
            'vehicle_model_id' => VehicleModel::firstOrCreate(['vehicle_model_api_id' => $car['model']['vehicle_model_api_id']], ['name' => $car['model']['name']])->id,
            'generation_id' => Generation::firstOrCreate(['generation_api_id' => $car['generation']['generation_api_id']], ['name' => $car['generation']['name']])->id,
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
            'cylinders' => $car['vehicle_record']['cylinders'],
            // Lot Data Processing
            'salvage_id' => $car['lot']['salvage_id'] ?? null,
            'lot_id' => $car['lot']['lot_id'] ?? null,
            'domain_id' =>  isset($car['lot']['domain'])
            ? Domain::firstOrCreate(
                ['domain_api_id' => $car['lot']['domain']['domain_api_id']],
                ['name' => $car['lot']['domain']['name']]
            )->id
            : null,
            'selling_branch' => $car['lot']['selling_branch'] ? SellingBranch::firstOrCreate(
                ['selling_branch_api_id' => $car['lot']['selling_branch']['selling_branch_api_id']],
                [
                    'name' => $car['lot']['selling_branch']['name'],
                    'link' => $car['lot']['selling_branch']['link'],
                    'number' => $car['lot']['selling_branch']['number'],
                    'domain_id' => $car['lot']['selling_branch']['domain_id'],
                ]
            ) : null,
            'external_id' => $car['lot']['external_id'] ?? null,
            'odometer_km' => $car['lot']['odometer_km'] ?? null,
            'odometer_mi' => $car['lot']['odometer_mi'] ?? null,
            'odometer_status' => $car['lot']['odometer_status'] ?? null,
            'estimate_repair_price' => $car['lot']['estimate_repair_price'] ?? null,
            'pre_accident_price' => $car['lot']['pre_accident_price'] ?? null,
            'clean_wholesale_price' => $car['lot']['clean_wholesale_price'] ?? null,
            'actual_cash_value' => $car['lot']['actual_cash_value'] ?? null,
            'sale_date' => $car['lot']['sale_date'] ?? null,
            'sale_date_updated_at' => $car['lot']['sale_date_updated_at'] ?? null,
            'bid' => $car['lot']['bid'] ?? null,
            'bid_updated_at' => $car['lot']['bid_updated_at'] ?? null,
            'buy_now' => $car['lot']['buy_now'] ?? null,
            'buy_now_updated_at' => $car['lot']['buy_now_updated_at'] ?? null,
            'final_bid' => $car['lot']['final_bid'] ?? null,
            'final_bid_updated_at' => $car['lot']['final_bid_updated_at'] ?? null,
            'keys_available' => $car['lot']['keys_available'] ?? null,
            'airbags' => $car['lot']['airbags'] ?? null,
            'grade_iaai' => $car['lot']['grade_iaai'] ?? null,
            'odometer_id' => Odometer::firstOrCreate(
                ['name' => $car['lot']['odometer']['name']]
            )->id,
            'seller_id' => Seller::firstOrCreate(
                ['seller_api_id' => $car['lot']['seller']['seller_api_id']],
                ['name' => $car['lot']['seller']['name']]
            )->id,
            'seller_type_id' => SellerType::firstOrCreate(
                ['seller_type_api_id' => $car['lot']['seller_type']['seller_type_api_id']],
                ['name' => $car['lot']['seller_type']['name']]
            )->id,
            'condition_id' => Condition::firstOrCreate(
                ['condition_api_id' => $car['lot']['condition']['condition_api_id']],
                ['name' => $car['lot']['condition']['name']]
            )->id,
            'status_id' =>  Status::firstOrCreate(
                ['status_api_id' => $car['lot']['status']['status_api_id']],
                ['name' => $car['lot']['status']['name']]
            )->id,
            'title_id' => Title::firstOrCreate(
                ['title_api_id' => $car['lot']['title']['title_api_id']],
                ['name' => $car['lot']['title']['name']]
            )->id,
            'detailed_title_id' => DetailedTitle::firstOrCreate(
                ['detailed_title_api_id' => $car['lot']['detailedTitle']['id']],
                ['name' => $car['lot']['detailedTitle']['name']]
            )->id,
            'damage_id' => $car['lot']['damageMain'] ? Damage::firstOrCreate(
                ['damage_api_id' => $car['lot']['damageMain']['damage_api_id']],
                ['name' => $car['lot']['damageMain']['name']]
            )->id : null,
            'damage_main' => $car['lot']['damageMain'] ? Damage::firstOrCreate(
                ['damage_api_id' => $car['lot']['damageMain']['damage_api_id']],
                ['name' => $car['lot']['damageMain']['name']]
            )->id : null,
            'damage_second' => $car['lot']['damageSecond'] ? Damage::firstOrCreate(
                ['damage_api_id' => $car['lot']['damageSecond']['damage_api_id']],
                ['name' => $car['lot']['damageSecond']['name']]
            )->id : null,
            'buy_now_id' => BuyNow::where('name', $car['lot']['buy_now'])->value('id'),
            'details' => $car['lot']['details'] ?? null,
            'location_id' => !empty($car['lot']['locationRecord']) && !empty($car['lot']['locationRecord']['location_api_id']) ? Location::firstOrCreate(
                ['location_api_id' => $car['lot']['locationRecord']['location_api_id']],
                [
                    'city_id' => !empty($car['lot']['city']) ? City::firstOrCreate(
                        ['city_api_id' => $car['lot']['city']['city_api_id']],
                        [
                            'state_id' => !empty($car['lot']['state']) ? State::firstOrCreate(
                                ['state_api_id' => $car['lot']['state']['state_api_id']],
                                [
                                    'country_id' => $car['lot']['country'] ? Country::firstOrCreate(
                                        ['iso' => $car['lot']['country']['iso']],
                                        ['name' => $car['lot']['country']['name']]
                                    )->id : null,
                                    'code' => $car['lot']['state']['code'],
                                    'name' => $car['lot']['state']['name']
                                ]
                            )->id : null,
                            'name' => $car['lot']['city']['name']
                        ]
                    ) : null,
                    'name' => trim($car['lot']['locationRecord']['name']) ?: 'Unnamed Location',
                    'latitude' => $car['lot']['locationRecord']['latitude'] ?? null,
                    'longitude' => $car['lot']['locationRecord']['longitude'] ?? null,
                    'postal_code' => trim($car['lot']['locationRecord']['postal_code']) ?: null,
                    'is_offsite' => $car['lot']['locationRecord']['is_offsite'] ?? false,
                    'raw' => $car['lot']['locationRecord']['raw'] ?? '{}'
                ]
            )->id : null,
            'image_id' => !empty($car['lot']['imageRecord']) ? Image::updateOrCreate(
                ['image_api_id' => $car['lot']['imageRecord']['image_api_id']],
                [
                    'small' => json_encode($car['lot']['imageRecord']['small'] ?? []),
                    'normal' => json_encode($car['lot']['imageRecord']['normal'] ?? []),
                    'big' => json_encode($car['lot']['imageRecord']['big'] ?? []),
                    'downloaded' => json_encode($car['lot']['imageRecord']['downloaded'] ?? []),
                    'exterior' => json_encode($car['lot']['imageRecord']['exterior'] ?? []),
                    'interior' => json_encode($car['lot']['imageRecord']['interior'] ?? []),
                    'video' => $car['lot']['imageRecord']['video'] ?? null,
                    'video_youtube_id' => $car['lot']['imageRecord']['video_youtube_id'] ?? null,
                    'external_panorama_url' => $car['lot']['imageRecord']['external_panorama_url'] ?? null,
                ]
            )->id : null,


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
