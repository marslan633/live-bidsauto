<?php

namespace App\Console\Commands;

use App\Models\CacheKey;
use App\Models\VehicleApiData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessFinalApiDataWithoutQueue extends Command
{
    protected $signature = 'process:final-api-data-without-queue';

    protected $description = 'Fetch data from API and push it to Redis';

    public function handle()
    {
        $startTime = microtime(true);
        $startDateTime = Carbon::now();

        // **Get Last Successful Cron Job Status**
        $lastCron = DB::table('cron_run_history')
            ->where('cron_name', 'process_final_vehicle_data')
            ->where('status', 'success')
            ->latest('start_time')
            ->first();

        $minutes = 400; // Default minutes value

        if ($lastCron && $lastCron->end_time) {
            $endTime = Carbon::parse($lastCron->end_time);
            $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));



            if ($timeDifference > 20) {
                $minutes = $timeDifference + 10;
            } elseif ($timeDifference === 20) {
                $minutes = $timeDifference + 5;
            }
        }


        // **Store Cron Job Status**
        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name' => 'process_final_vehicle_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $perPage = 1000;
        $baseUrl = 'http://82.197.92.223/api/vehicles';

        $apiUrl = "{$baseUrl}?page=1&size={$perPage}";


        try {
            do {
                // **Fetch Fresh Data from API**
                $response = Http::timeout(120)
                    ->retry(3, 1000)
                    ->post($apiUrl);

                if (! $response->successful()) {
                    $this->error('❌ Failed to fetch API data.');
                    // \Log::error('❌ Failed to fetch API data.');
                    break;
                }

                $data = $response->json()['data'] ?? null;

                if (!empty($data)) {
                    $batchData = [];
                    foreach ($data as $car) {
                        $batchData[] = $this->prepareCarData((array) $car);
                    }

                    if (count($batchData) > 0) {
                        Log::info('Batch Inserted');
                        $this->insertBatch($batchData);
                        $batchData = []; // Reset batch
                    }else{
                        Log::info('Batch Condition Not Meet');
                    }
                }

                // **Get 'next' page URL**
                $nextUrl = $response->json()['links']['next'] ?? null;
                $this->info('Next URL.', $nextUrl);
                $apiUrl = $nextUrl ?: null;

            } while ($nextUrl !== null);

            // **Mark Cron as Success**
            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            // \Log::error("❌ Error: " . $e->getMessage());

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);
        }
    }

    public function prepareCarData(array $car)
    {
        // Log::info('Car Dara', ['CarData' => json_encode($car)]);
        $year = null;
        if (!isset($car['year'])) {
            $year = DB::connection('mysql')->table('years')->insertGetId(['name' => $car['year']]);
        }
        Log::info('Year', ['data' => $year]);

        $model_id = DB::connection('mysql')->table('vehicle_models')
                ->where('vehicle_model_api_id', $car['vehicle_model']['vehicle_model_api_id'])
                ->value('id') // Fetch only the 'id' column for efficiency
                ?? DB::connection('mysql')->table('vehicle_models')->insertGetId([
                    'vehicle_model_api_id' => $car['vehicle_model']['vehicle_model_api_id'],
                    'name' => $car['vehicle_model']['name'],
                ]);

        Log::info('Model', ['data' => $model_id]);


        $imageRecord = $car['image'] ?? [];

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

        $domain_id = isset($car['domain_api_id'])
        ?  DB::connection('mysql')->table('domains')
                ->where('domain_api_id', $car['domain_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('domains')->insertGetId([
                    'domain_api_id' => $car['domain_api_id'],
                    'name' => $car['name'],
                ])
        : null;
                Log::info('Domain', ['data' => $domain_id]);

        $selling_branch_id = isset($car['selling_branch'])
        ?  DB::connection('mysql')->table('selling_branches')
                ->where('selling_branch_api_id', $car['selling_branch']['selling_branch_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('selling_branches')->insertGetId([
                    'selling_branch_api_id' => $car['selling_branch']['selling_branch_api_id'],
                    'name' => $car['selling_branch']['name'],
                    'link' => $car['selling_branch']['link'],
                    'number' => $car['selling_branch']['number'],
                    'domain_id' => $domain_id, // Use the computed domain_id
                ])
        : null;
        Log::info('Seller Branch', ['data' => $selling_branch_id]);

        $odometer_id = null;

        $seller_id = DB::connection('mysql')->table('sellers')
            ->where('seller_api_id', $car['seller']['seller_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('sellers')->insertGetId([
                'seller_api_id' => $car['seller']['seller_api_id'],
                'name' => $car['seller']['name']
            ]);
        Log::info('Seller', ['data' => $seller_id]);

        $seller_type_id = DB::connection('mysql')->table('seller_types')
            ->where('seller_type_api_id', $car['seller_type']['seller_type_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('seller_types')->insertGetId([
                'seller_type_api_id' => $car['seller_type']['seller_type_api_id'],
                'name' => $car['seller_type']['name']
            ]);
        Log::info('Seller Type', ['data' => $seller_type_id]);

        $condition_id = DB::connection('mysql')->table('conditions')
            ->where('condition_api_id', $car['condition']['condition_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('conditions')->insertGetId([
                'condition_api_id' => $car['condition']['condition_api_id'],
                'name' => $car['condition']['name']
            ]);
        Log::info('Condition', ['data' => $condition_id]);

        $status_id = DB::connection('mysql')->table('statuses')
            ->where('status_api_id', $car['status']['status_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('statuses')->insertGetId([
                'status_api_id' => $car['status']['status_api_id'],
                'name' => $car['status']['name']
            ]);
        Log::info('Status', ['data' => $status_id]);

        $title_id = !empty($car['title_relation'])
            ? DB::connection('mysql')->table('titles')
                ->where('title_api_id', $car['title_relation']['title_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('titles')->insertGetId([
                    'title_api_id' => $car['title_relation']['title_api_id'],
                    'name' => $car['title_relation']['name']
                ])
            : null;
            Log::info('Title', ['data' => $title_id]);

        $detailed_title_id = DB::connection('mysql')->table('detailed_titles')
            ->where('detailed_title_api_id', $car['detailed_title']['detailed_title_api_id'])
            ->value('id')
            ?? DB::connection('mysql')->table('detailed_titles')->insertGetId([
                'detailed_title_api_id' => $car['detailed_title']['detailed_title_api_id'],
                'name' => $car['detailed_title']['name']
            ]);
            Log::info('Detailed Title', ['data' => $detailed_title_id]);

        $damage_id = !empty($car['damage_main'])
            ? DB::connection('mysql')->table('damages')
                ->where('damage_api_id', $car['damage_main']['damage_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('damages')->insertGetId([
                    'damage_api_id' => $car['damage_main']['damage_api_id'],
                    'name' => $car['damage_main']['name']
                ])
            : null;
        Log::info('Damage Main', ['data' => $damage_id]);

        $damage_second = !empty($car['damage_second'])
            ? DB::connection('mysql')->table('damages')
                ->where('damage_api_id', $car['damage_second']['damage_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('damages')->insertGetId([
                    'damage_api_id' => $car['damage_second']['damage_api_id'],
                    'name' => $car['damage_second']['name']
                ])
            : null;
        Log::info('Damage Second', ['data' => $damage_second]);

        $country_id = null;

        $state_id = null;

        $city_id = null;

        $location_id = !empty($car['location']['location_api_id'])
            ? DB::connection('mysql')->table('locations')
                ->where('location_api_id', $car['location']['location_api_id'])
                ->value('id')
                ?? DB::connection('mysql')->table('locations')->insertGetId([
                    'location_api_id' => $car['location']['location_api_id'],
                    'city_id' => $city_id,
                    'name' => trim($car['location']['name']) ?: 'Unnamed Location',
                    'latitude' => $car['location']['latitude'] ?? null,
                    'longitude' => $car['location']['longitude'] ?? null,
                    'postal_code' => trim($car['location']['postal_code']) ?: null,
                    'is_offsite' => $car['location']['is_offsite'] ?? false,
                    'raw' => $car['location']['raw'] ?? '{}'
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
            'api_id' => $car['api_id'] ?? null,
            'year' => $car['year'] ?? null,
            'year_id' => $year,
            'title' => $car['title'] ?? null,
            'vin' => $car['vin'] ?? null,
            'cylinders' => $car['cylinders'] ?? null,
            // Lot Data Processing
            'salvage_id' => $car['salvage_id'] ?? null,
            'lot_id' => $car['lot_id'] ?? null,
            'domain_id' =>  $domain_id,
            'selling_branch' => $selling_branch_id,
            'external_id' => $car['external_id'] ?? null,
            'odometer_km' => $car['odometer_km'] ?? null,
            'odometer_mi' => $car['odometer_mi'] ?? null,
            'odometer_status' => $car['odometer_status'] ?? null,
            'estimate_repair_price' => $car['estimate_repair_price'] ?? null,
            'pre_accident_price' => $car['pre_accident_price'] ?? null,
            'clean_wholesale_price' => $car['clean_wholesale_price'] ?? null,
            'actual_cash_value' => $car['actual_cash_value'] ?? null,
            'sale_date' => $car['sale_date'] ?? null,
            'sale_date_updated_at' => $car['sale_date_updated_at'] ?? null,
            'bid' => $car['bid'] ?? null,
            'bid_updated_at' => $car['bid_updated_at'] ?? null,
            'buy_now' => $car['buy_now'] ?? null,
            'buy_now_updated_at' => $car['buy_now_updated_at'] ?? null,
            'final_bid' => $car['final_bid'] ?? null,
            'final_bid_updated_at' => $car['final_bid_updated_at'] ?? null,
            'keys_available' => $car['keys_available'] ?? null,
            'airbags' => $car['airbags'] ?? null,
            'grade_iaai' => $car['grade_iaai'] ?? null,
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
            'buy_now_id' => DB::connection('mysql')->table('buy_nows')->where('name', $car['buy_now'])->value('id') ?? null,
            'details' => $car['details'] ?? null,
            'location_id' => $location_id,
            'image_id' => $imageId,
        ];
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

        } catch (\Exception $e) {

            // Mark cache as pending in case of failure

            Log::info("Batch insert failed: " . $e->getMessage());
        }
    }
}
