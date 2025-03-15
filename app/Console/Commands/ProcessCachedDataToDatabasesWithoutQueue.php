<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCachedDataToDatabaseJob;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Mail, Log};
use App\Mail\CronJobFailedMail;
use App\Models\VehicleProcessCachedApiData;

class ProcessCachedDataToDatabasesWithoutQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:process-cached-data-to-databases-without-queue';

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
        Log::info("Process started at: " . $startDateTime);

        $cronRun = null;

        try{
            $cronRun = DB::connection('mysql')->table('cron_run_history')->insertGetId([
                'cron_name' => 'process_cached_data_to_database',
                'start_time' => $startDateTime,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $cacheKeys = VehicleProcessCachedApiData::orderBy('created_at', 'asc')->limit(100)->get();

            if (count($cacheKeys) == 0) {
                $this->info("No Data Pending to process");
            }


        }catch(\Exception $e){
            if($cronRun !== null){
                $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
            }
            return;
        }

        // **Batch processing setup**
        // Initialize an empty array to hold the jobs
        // Iterate over the cache keys and create jobs
        foreach ($cacheKeys as $itemKey) {
            try {
                $data = $itemKey->cache_value;
                if (!$data) {
                    Log::warning("No data found for key: {$itemKey->id}");
                    return;
                }

                $batchData = [];
                foreach ($data as $car) {
                    $batchData[] = $this->prepareCarData((array) $car);
                }

                if (count($batchData) > 0) {
                    Log::info('Batch Inserted');
                    $this->insertBatch($batchData, $itemKey->id);
                    $batchData = []; // Reset batch
                }else{
                    Log::info('Batch Condition Not Meet');
                }

            } catch (\Exception $e) {
                Log::error("Error processing key {$itemKey->id}: " . $e->getMessage());
            }
        }

        if($cronRun){
            DB::connection('mysql')->table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);
        }


    }

    /**
     * Handle cron job failure and send email notification.
     */
    private function handleCronError($cronRun, $errorMessage)
    {
        Log::error($errorMessage);
        DB::table('cron_run_history')->where('id', $cronRun)->update([
            'end_time' => Carbon::now(),
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        $adminEmails = explode(',', env('ADMIN_EMAIL'));
        Mail::to($adminEmails)->send(new CronJobFailedMail($errorMessage, 'process_cached_data'));
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
    public function insertBatch(array $batchData, $cacheKey)
    {
        Log::info('Starting Batch Insertion');
        try {
            if (empty($batchData)) {
                return;
            }


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

            VehicleProcessCachedApiData::where('id', $cacheKey)->delete();




        } catch (\Exception $e) {

            // Mark cache as pending in case of failure

            Log::info("Batch insert failed: " . $e->getMessage());
        }
    }
}
