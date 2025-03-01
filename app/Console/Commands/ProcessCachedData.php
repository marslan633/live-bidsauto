<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, CacheKey,
    RemoteCacheKey
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;

class ProcessCachedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cached-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch data from cache and save it into the database';

    /**
     * Execute the console command.
     */
public function handle()
{
    $startDateTime = Carbon::now();
    $this->info("Process started at: " . $startDateTime);
    \Log::info("Process started at: " . $startDateTime);

    DB::beginTransaction();
    try {
        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name' => 'process_cached_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lock the cache keys for update
        $cacheKeys = CacheKey::where('cache_key', 'like', 'vehicle_data%')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->take(10)
            ->get();

        if ($cacheKeys->isEmpty()) {
            $this->info("No pending cache keys found.");
            DB::commit();
            return;
        }

        $cacheKeyIds = $cacheKeys->pluck('id')->toArray();

        // Update status in bulk
        CacheKey::whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        $this->handleCronError($cronRun, "Error fetching cache keys: " . $e->getMessage());
        return;
    }

    foreach ($cacheKeys as $cacheKey) {
        try {
            $key = $cacheKey->cache_key;
            $data = Cache::get($key);

            if (!$data) {
                $this->info("No data found for key: {$key}");
                CacheKey::where('cache_key', $key)->delete();
                continue;
            }

            foreach ($data as $car) {
                $processedData = $this->convertAndStoreDataToRedis($car);

                // Generate a unique cache key
                $cacheKey = 'vehicle_data_' . now()->format('Y_m_d_H_i_s');
                $expiresAt = now()->addMinutes(300);

                // Store in remote Redis (use 'redis_cache' instead of default Redis)
                Cache::store('redis_cache')->put($cacheKey, json_encode($processedData), $expiresAt);

                // Save cache details to the database
                RemoteCacheKey::updateOrCreate(
                    ['cache_key' => $cacheKey],
                    [
                        'status' => 'pending',
                        'expires_at' => $expiresAt,
                    ]
                );

                $this->info("Data processed and stored in remote Redis: " . json_encode($processedData));
            }

            // Remove cache key from DB and Redis
            CacheKey::where('cache_key', $key)->delete();
            Cache::forget($key);

        } catch (\Exception $e) {
            \Log::error("Error processing key {$key}: " . $e->getMessage());
            $cacheKey->update(['status' => 'pending']); // Revert status
        }
    }

    DB::table('cron_run_history')->where('id', $cronRun)->update([
        'end_time' => Carbon::now(),
        'status' => 'success',
        'updated_at' => now(),
    ]);
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


    private function convertAndStoreDataToRedis(array $car)
    {
        // Initialize variables as null
        $model = null;
        $generation = null;

        // Define placeholders for "Unknown"
        $unknownApiId = 0;
        $unknownName = 'unknown';

        $convertedData = [];


        // Process Manufacturer
        $convertedData['manufacturer'] = $car['manufacturer']
        ?
            [
                'manufacturer_api_id' => $car['manufacturer']['id'],
                'name' => $car['manufacturer']['name']
            ]
        :
            [
                'manufacturer_api_id' => $unknownApiId,
                'name' => $unknownName
            ];


        // Process Model
        $convertedData['model'] =  $car['model']
        ?
            [
                'vehicle_model_api_id' => $car['model']['id'],
                'name' => $car['model']['name']
            ]
        :
            [
                'vehicle_model_api_id' => $unknownApiId,
                'name' => $unknownName,
            ];


        // Process Generation
        $convertedData['generation'] = $car['generation']
        ?
            [
                'generation_api_id' => $car['generation']['id'],
                'name' => $car['generation']['name']
            ]
        :
            [
                'generation_api_id' => $unknownApiId,
                'name' => $unknownName,
            ];


        // Process Year
        $convertedData['year'] = $car['year'] ? ['name' => $car['year']] : null;

        // Process BodyType
        $convertedData['body_type'] = $car['body_type']
        ?
            [
                'body_type_api_id' => $car['body_type']['id'],
                'name' => $car['body_type']['name']
            ]
        :
            [
                'body_type_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Color
        $convertedData['color'] = $car['color']
        ?
            [
                'color_api_id' => $car['color']['id'],
                'name' => $car['color']['name']
            ]
        :
            [
                'color_api_id' => $unknownApiId,
                'name' => $unknownName
            ];



        // Process Engine
        $convertedData['engine'] = isset($car['engine']) && is_array($car['engine'])
        ?
            [
                'engine_api_id' => $car['engine']['id'],
                'name' => $car['engine']['name']
            ]
        :
            [
                'engine_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Transmission
        $convertedData['transmission'] = isset($car['transmission']) && is_array($car['transmission'])
        ?
            [
                'transmission_api_id' => $car['transmission']['id'],
                'name' => $car['transmission']['name']
            ]
        :
            [
                'transmission_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Drive Wheel
        $convertedData['drive_wheel'] = isset($car['drive_wheel']) && is_array($car['drive_wheel'])
        ?
            [
                'drive_wheel_api_id' => $car['drive_wheel']['id'],
                'name' => $car['drive_wheel']['name']
            ]
        :
            [
                'drive_wheel_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Vehicle Type
        $convertedData['vehicle_type'] = isset($car['vehicle_type']) && is_array($car['vehicle_type'])
        ?
            [
                'vehicle_type_api_id' => $car['vehicle_type']['id'],
                'name' => $car['vehicle_type']['name']
            ]
        :
            [
                'vehicle_type_api_id' => $unknownApiId,
                'name' => $unknownName
            ];



        // Process Fuel
        $convertedData['fuel'] = isset($car['fuel']) && is_array($car['fuel'])
        ?
            [
                'fuel_api_id' => $car['fuel']['id'],
                'name' => $car['fuel']['name']
            ]
        :
            [
                'fuel_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Vehicle Record
        $convertedData['vehicle_record'] = [
            'api_id' => $car['id'] ?? null,
            'year' => $car['year'] ?? null,
            'title' => $car['title'] ?? null,
            'vin' => $car['vin'] ?? null,
            'cylinders' => $car['cylinders'] ?? null,
            // 'processed_at' => Carbon::now(),
            // 'is_new' => true,
        ];


        $convertedData['lots'] = $car['lots'];
        $lot = $convertedData['lots'][0];

        $processLotData = $this->processLotData($lot);
        $convertedData['vehicle_record'] = array_merge($convertedData['vehicle_record'], $processLotData);

        return $convertedData;
    }


    private function processLotData($lot)
    {
        $unknownApiId = 0;
        $unknownName = 'unknown';
        // Determine buy_now_id based on buy_now value
        $buyNowValue = $lot['buy_now'] ?? null;
        $buyNowId = null;

        $lotConveredData = [];


        if ($buyNowValue === 0 || is_null($buyNowValue)) {
            $lotConveredData['buy_now']  = 'buyNowWithoutPrice';
        } elseif (is_numeric($buyNowValue) && $buyNowValue > 0) {
            $lotConveredData['buy_now']  = 'buyNowWithPrice';
        }

        $lotConveredData['domain'] = isset($lot['domain']) && is_array($lot['domain'])
        ?
            [
                'domain_api_id' => $lot['domain']['id'],
                'name' => $lot['domain']['name']
            ]
        :
            null;


        // Process Selling Branch
        $lotConveredData['sellingBranch'] = isset($lot['selling_branch']) && is_array($lot['selling_branch'])
        ?
            [
                'selling_branch_api_id' => $lot['selling_branch']['id'],
                'name' => $lot['selling_branch']['name'],
                'link' => $lot['selling_branch']['link'],
                'number' => $lot['selling_branch']['number'],
                'domain_id' => $lot['selling_branch']['domain_id']
            ]
        :
            null;


        // Process Odometer
        $lotConvertedData['odometer'] = isset($lot['odometer']['mi'])
        ?
            [
                'name' => $lot['odometer']['mi']
            ]
        :
            [
                'name' => $unknownName
            ];


        // Process Seller
        $lotConvertedData['seller'] = isset($lot['seller']) && is_array($lot['seller'])
        ?
            [
                'seller_api_id' => $lot['seller']['id'],
                'name' => $lot['seller']['name']
            ]
        :
            [
                'seller_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Seller Type
        $lotConvertedData['sellerType'] = isset($lot['seller_type']) && is_array($lot['seller_type'])
        ?
            [
                'seller_type_api_id' => $lot['seller_type']['id'],
                'name' => $lot['seller_type']['name']
            ]
        :
            [
                'seller_type_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        $unknownConditionApiId = 100;

        // Process Condition
        $lotConvertedData['condition'] = isset($lot['condition']) && is_array($lot['condition'])
        ?
            [
                'condition_api_id' => $lot['condition']['id'],
                'name' => $lot['condition']['name']
            ]
        :
            [
                'condition_api_id' => $unknownConditionApiId,
                'name' => 'unknown'
            ];

        // Process Status
        $lotConvertedData['status'] = isset($lot['status']) && is_array($lot['status'])
        ?
            [
                'status_api_id' => $lot['status']['id'],
                'name' => $lot['status']['name']
            ]
        :
            [
                'status_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Title
        $lotConvertedData['title'] = isset($lot['title']) && is_array($lot['title'])
        ?
            [
                'title_api_id' => $lot['title']['id'],
                'name' => $lot['title']['name']
            ]
        :
            [
                'title_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Detailed Title
        $lotConvertedData['detailedTitle'] = isset($lot['detailed_title']) && is_array($lot['detailed_title'])
        ?
            [
                'detailed_title_api_id' => $lot['detailed_title']['id'],
                'name' => $lot['detailed_title']['name']
            ]
        :
            [
                'detailed_title_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

        // Process Damage
        $lotConvertedData['damageMain'] = isset($lot['damage']['main']) && is_array($lot['damage']['main'])
        ?
            [
                'damage_api_id' => $lot['damage']['main']['id'],
                'name' => $lot['damage']['main']['name']
            ]
        :
            null;

        $lotConvertedData['damageSecond'] = isset($lot['damage']['second']) && is_array($lot['damage']['second'])
        ?
            [
                'damage_api_id' => $lot['damage']['second']['id'],
                'name' => $lot['damage']['second']['name']
            ]
        :
            null;


        $location = $lot['location'];
        $lotConvertedData['location'] = $lot['location'];
        // Handle Country
        $lotConvertedData['country'] = $location['country'] ? [
                'iso' => $location['country']['iso'],
                'name' => $location['country']['name']
            ]
         : null;
        $country = $lotConvertedData['country'];

        // Initialize variables to null
        $lotConvertedData['state'] = null;
        $lotConvertedData['city'] = null;
        $locationRecord = null;

        // Only proceed if the state is not null or empty
        if (!empty($location['state'])) {
            // Handle State
            $lotConvertedData['state'] = [
                'state_api_id' => $location['state']['id'],
                    'country_id' => $country?->id,
                    'code' => $location['state']['code'],
                    'name' => $location['state']['name']
            ];
            // Handle City
            if (!empty($location['city'])) {
                $lotConvertedData['city'] = ['city_api_id' => $location['city']['id'],
                    'name' => $location['city']['name']
                ];
                // Handle Location
                if (!empty($location['location']) && !empty($location['location']['id'])) {
                    $lotConvertedData['locationRecord'] =   [
                        'location_api_id' => $location['location']['id'],
                        'name' => trim($location['location']['name']) ?: 'Unnamed Location',
                        'latitude' => $location['latitude'] ?? null,
                        'longitude' => $location['longitude'] ?? null,
                        'postal_code' => trim($location['postal_code']) ?: null,
                        'is_offsite' => $location['is_offsite'] ?? false,
                        'raw' => $location['raw'] ?? '{}'
                    ];
                }
            }
        }
        // Process Images
        if (!empty($lot['images'])) {
            $imagesData = $lot['images'];

            $lotConvertedData['imageRecord'] = [
                'image_api_id' => $imagesData['id'],
                'small' => json_encode($imagesData['small'] ?? []),
                'normal' => json_encode($imagesData['normal'] ?? []),
                'big' => json_encode($imagesData['big'] ?? []),
                'downloaded' => json_encode($imagesData['downloaded'] ?? []),
                'exterior' => json_encode($imagesData['exterior'] ?? []),
                'interior' => json_encode($imagesData['interior'] ?? []),
                'video' => $imagesData['video'] ?? null,
                'video_youtube_id' => $imagesData['video_youtube_id'] ?? null,
                'external_panorama_url' => $imagesData['external_panorama_url'] ?? null,
            ];

        }

        // Update Vehicle Record with Lot Information
        $lotConvertedData['salvage_id'] = $lot['id'] ?? null;
        $lotConvertedData['lot_id'] = $lot['lot'] ?? null;
        $lotConvertedData['external_id'] = $lot['external_id'] ?? null;
        $lotConvertedData['odometer_km'] = $lot['odometer']['km'] ?? null;
        $lotConvertedData['odometer_mi'] = $lot['odometer']['mi'] ?? null;
        $lotConvertedData['odometer_status'] = $lot['odometer']['status']['name'] ?? null;
        $lotConvertedData['estimate_repair_price'] = $lot['estimate_repair_price'] ?? null;
        $lotConvertedData['pre_accident_price'] = $lot['pre_accident_price'] ?? null;
        $lotConvertedData['clean_wholesale_price'] = $lot['clean_wholesale_price'] ?? null;
        $lotConvertedData['actual_cash_value'] = $lot['actual_cash_value'] ?? null;
        $lotConvertedData['sale_date'] = $lot['sale_date'] ?? null;
        $lotConvertedData['sale_date_updated_at'] = $lot['sale_date_updated_at'] ?? null;
        $lotConvertedData['bid'] = $lot['bid'] ?? null;
        $lotConvertedData['bid_updated_at'] = $lot['bid_updated_at'] ?? null;
        $lotConvertedData['buy_now'] = $lot['buy_now'] ?? null;
        $lotConvertedData['buy_now_updated_at'] = $lot['buy_now_updated_at'] ?? null;
        $lotConvertedData['final_bid'] = $lot['final_bid'] ?? null;
        $lotConvertedData['final_bid_updated_at'] = $lot['final_bid_updated_at'] ?? null;
        $lotConvertedData['keys_available'] = $lot['keys_available'] ?? null;
        $lotConvertedData['airbags'] = $lot['airbags']['name'] ?? null;
        $lotConvertedData['grade_iaai'] = $lot['grade_iaai'] ?? null;
        $lotConvertedData['details'] = $lot['details'] ?? null;


        return $lotConvertedData;

    }
}
