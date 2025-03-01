<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, CacheKey,
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronJobFailedMail;
class ProcessCachedDataToDatabasesOne extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-cached-data-to-databases-one';

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
        }catch(\Exception $e){
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
                    $this->processCarData($car);
                    $this->info("Start Processing Cached Data For Remove Redis");

                    $this->info("Data Process For Removed Redis:");
                }

                // Remove cache key from DB and Redis
                CacheKey::where('cache_key', $key)->delete();
                Cache::forget($key);

            } catch (\Exception $e) {
                \Log::error("Error processing key {$key}: " . $e->getMessage());
                $this->info("Data Process Error For Removed Redis:");
                // $cacheKey->update(['status' => 'pending']); // Revert status
            }
        }

        DB::table('cron_run_history')->where('id', $cronRun)->update([
            'end_time' => Carbon::now(),
            'status' => 'success',
            'updated_at' => now(),
        ]);


    }


    private function processCarData(array $car)
    {
        // Define placeholders for "Unknown"
        $unknownApiId = 0;
        $unknownName = 'unknown';

        // Process Manufacturer
        $manufacturer = Manufacturer::firstOrCreate(
            ['manufacturer_api_id' => $car['manufacturer']['manufacturer_api_id']],
            ['name' => $car['manufacturer']['name']]
        );

        // Process Model
        $model = VehicleModel::firstOrCreate(
            ['vehicle_model_api_id' => $car['model']['vehicle_model_api_id']],
            [
                'name' => $car['model']['name'],
                'manufacturer_id' => $manufacturer->id
            ]
        );

        // Process Generation
        $generation = Generation::firstOrCreate(
            ['generation_api_id' => $car['generation']['generation_api_id']],
            [
                'name' => $car['generation']['name'],
                'manufacturer_id' => $manufacturer->id,
                'model_id' => $model->id
            ]
        );

        // Process Year
        if ($car['year']) {
            $year = Year::firstOrCreate(
                ['name' => $car['year']]
            );
        }

        // Process BodyType
        $bodyType = BodyType::firstOrCreate(
            ['body_type_api_id' => $car['body_type']['body_type_api_id']],
            [
                'name' => $car['body_type']['name'],
            ]
        );

        // Process Color
        $color = Color::firstOrCreate(
            ['color_api_id' => $car['color']['color_api_id']],
            ['name' => $car['color']['name']]
        );

        // Process Engine
        $engine = Engine::firstOrCreate(
            ['engine_api_id' => $car['engine']['engine_api_id']],
            ['name' => $car['engine']['name']]
        );

        // Process Transmission
        $transmission = Transmission::firstOrCreate(
            ['transmission_api_id' => $car['transmission']['transmission_api_id']],
            ['name' => $car['transmission']['name']]
        );

        // Process Drive Wheel
        $driveWheel = DriveWheel::firstOrCreate(
            ['drive_wheel_api_id' => $car['drive_wheel']['drive_wheel_api_id']],
            ['name' => $car['drive_wheel']['name']]
        );

        // Process Vehicle Type
        $vehicleType = VehicleType::firstOrCreate(
            ['vehicle_type_api_id' => $car['vehicle_type']['vehicle_type_api_id']],
            ['name' => $car['vehicle_type']['name']]
        );

        // Process Fuel
        $fuel = Fuel::firstOrCreate(
            ['fuel_api_id' => $car['fuel']['fuel_api_id']],
            ['name' => $car['fuel']['name']]
        );

        // Process Vehicle Record
        $vehicleRecord = VehicleRecord::updateOrCreate(
            ['api_id' => $car['vehicle_record']['api_id']],
            [
                'year' => $car['vehicle_record']['year'],
                'year_id' => $year?->id,
                'title' => $car['vehicle_record']['title'],
                'vin' => $car['vehicle_record']['vin'],
                'manufacturer_id' => $manufacturer?->id,
                'vehicle_model_id' => $model?->id,
                'generation_id' => $generation?->id,
                'body_type_id' => $bodyType?->id,
                'color_id' => $color?->id,
                'engine_id' => $engine?->id,
                'transmission_id' => $transmission?->id,
                'drive_wheel_id' => $driveWheel?->id,
                'vehicle_type_id' => $vehicleType?->id,
                'fuel_id' => $fuel?->id,
                'cylinders' => $car['vehicle_record']['cylinders'],
                // 'processed_at' => Carbon::now(),
                // 'is_new' => true,
            ]
        );
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

        // Process lots
        $this->processLot($vehicleRecord, $car['vehicle_record']);
    }

    private function processLot($vehicleRecord, $lot)
    {

        $buyNowId = BuyNow::where('name', $lot['buy_now'])->value('id');

        // Process Seller
        $domain = $lot['domain'] ? Domain::firstOrCreate(
            ['domain_api_id' => $lot['domain']['domain_api_id']],
            ['name' => $lot['domain']['name']]
        ) : null;

        // Process Selling Branch
        $sellingBranch = $lot['selling_branch'] ? SellingBranch::firstOrCreate(
            ['selling_branch_api_id' => $lot['selling_branch']['selling_branch_api_id']],
            [
                'name' => $lot['selling_branch']['name'],
                'link' => $lot['selling_branch']['link'],
                'number' => $lot['selling_branch']['number'],
                'domain_id' => $lot['selling_branch']['domain_id'],
            ]
        ) : null;

        // Process Odometer
        $odometer = Odometer::firstOrCreate(
            ['name' => $lot['odometer']['name']]
        );


        // Process Seller
        $seller = Seller::firstOrCreate(
            ['seller_api_id' => $lot['seller']['seller_api_id']],
            ['name' => $lot['seller']['name']]
        );

        // Process Seller Type
        $sellerType = SellerType::firstOrCreate(
            ['seller_type_api_id' => $lot['seller_type']['seller_type_api_id']],
            ['name' => $lot['seller_type']['name']]
        );

        // Process Condition
        $condition = Condition::firstOrCreate(
            ['condition_api_id' => $lot['condition']['condition_api_id']],
            ['name' => $lot['condition']['name']]
        );

        // Process Status
        $status = Status::firstOrCreate(
            ['status_api_id' => $lot['status']['status_api_id']],
            ['name' => $lot['status']['name']]
        );

        // Process Title
        $title = Title::firstOrCreate(
            ['title_api_id' => $lot['title']['title_api_id']],
            ['name' => $lot['title']['name']]
        );
        // Process Detailed Title
        $detailedTitle = DetailedTitle::firstOrCreate(
            ['detailed_title_api_id' => $lot['detailedTitle']['id']],
            ['name' => $lot['detailedTitle']['name']]
        );

        // Process Damage
        $damageMain = $lot['damageMain'] ? Damage::firstOrCreate(
            ['damage_api_id' => $lot['damageMain']['damage_api_id']],
            ['name' => $lot['damageMain']['name']]
        ) : null;

        $damageSecond = $lot['damageSecond'] ? Damage::firstOrCreate(
            ['damage_api_id' => $lot['damageSecond']['damage_api_id']],
            ['name' => $lot['damageSecond']['name']]
        ) : null;

        $location = $lot['location'];
        // Handle Country
        $country = $location['country'] ? Country::firstOrCreate(
            ['iso' => $location['country']['iso']],
            ['name' => $location['country']['name']]
        ) : null;


        // Only proceed if the state is not null or empty
        if (!empty($lot['state'])) {
            // Handle State
            $state = State::firstOrCreate(
                ['state_api_id' => $lot['state']['state_api_id']],
                [
                    'country_id' => $country?->id,
                    'code' => $lot['state']['code'],
                    'name' => $lot['state']['name']
                ]
            );

            // Handle City
            if (!empty($lot['city'])) {
                $city = City::firstOrCreate(
                    ['city_api_id' => $lot['city']['city_api_id']],
                    [
                        'state_id' => $state->id,
                        'name' => $lot['city']['name']
                    ]
                );

                // Handle Location
                if (!empty($lot['locationRecord']) && !empty($lot['locationRecord']['location_api_id'])) {
                    $locationRecord = Location::firstOrCreate(
                        ['location_api_id' => $lot['locationRecord']['location_api_id']],
                        [
                            'city_id' => $city->id,
                            'name' => trim($lot['locationRecord']['name']) ?: 'Unnamed Location',
                            'latitude' => $lot['locationRecord']['latitude'] ?? null,
                            'longitude' => $lot['locationRecord']['longitude'] ?? null,
                            'postal_code' => trim($lot['locationRecord']['postal_code']) ?: null,
                            'is_offsite' => $lot['locationRecord']['is_offsite'] ?? false,
                            'raw' => $lot['locationRecord']['raw'] ?? '{}'
                        ]
                    );
                }
            }
        }


        // Process Images
        if (!empty($lot['imageRecord'])) {
            $imagesData = $lot['imageRecord'];

            $imageRecord = Image::updateOrCreate(
                ['image_api_id' => $imagesData['image_api_id']],
                [
                    'small' => json_encode($imagesData['small'] ?? []),
                    'normal' => json_encode($imagesData['normal'] ?? []),
                    'big' => json_encode($imagesData['big'] ?? []),
                    'downloaded' => json_encode($imagesData['downloaded'] ?? []),
                    'exterior' => json_encode($imagesData['exterior'] ?? []),
                    'interior' => json_encode($imagesData['interior'] ?? []),
                    'video' => $imagesData['video'] ?? null,
                    'video_youtube_id' => $imagesData['video_youtube_id'] ?? null,
                    'external_panorama_url' => $imagesData['external_panorama_url'] ?? null,
                ]
            );
        }

        // Update Vehicle Record with Lot Information
        $vehicleRecord->update([
            'salvage_id' => $lot['salvage_id'] ?? null,
            'lot_id' => $lot['lot_id'] ?? null,
            'domain_id' => $domain?->id,
            'external_id' => $lot['external_id'] ?? null,
            'odometer_km' => $lot['odometer_km'] ?? null,
            'odometer_mi' => $lot['odometer_mi'] ?? null,
            'odometer_id' => $odometer?->id,
            'odometer_status' => $lot['odometer_status'] ?? null,
            'estimate_repair_price' => $lot['estimate_repair_price'] ?? null,
            'pre_accident_price' => $lot['pre_accident_price'] ?? null,
            'clean_wholesale_price' => $lot['clean_wholesale_price'] ?? null,
            'actual_cash_value' => $lot['actual_cash_value'] ?? null,
            'sale_date' => $lot['sale_date'] ?? null,
            'sale_date_updated_at' => $lot['sale_date_updated_at'] ?? null,
            'bid' => $lot['bid'] ?? null,
            'bid_updated_at' => $lot['bid_updated_at'] ?? null,
            'buy_now' => $lot['buy_now'] ?? null,
            'buy_now_updated_at' => $lot['buy_now_updated_at'] ?? null,
            'final_bid' => $lot['final_bid'] ?? null,
            'final_bid_updated_at' => $lot['final_bid_updated_at'] ?? null,
            'status_id' => $status?->id,
            'seller_id' => $seller?->id,
            'seller_type_id' => $sellerType?->id,
            'title_id' => $title?->id,
            'detailed_title_id' => $detailedTitle?->id,
            'damage_id' => $damageMain?->id,
            'damage_main' => $damageMain?->id,
            'damage_second' => $damageSecond?->id,
            'keys_available' => $lot['keys_available'] ?? null,
            'airbags' => $lot['airbags'] ?? null,
            'condition_id' => $condition?->id,
            'grade_iaai' => $lot['grade_iaai'] ?? null,
            'image_id' => $imageRecord->id ?? null,
            'country_id' => $country?->id,
            'state_id' => $state?->id,
            'city_id' => $city?->id,
            'location_id' => $locationRecord?->id,
            'selling_branch' => $sellingBranch?->id,
            'details' => $lot['details'] ?? null,
            'buy_now_id' => $buyNowId,
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
}
