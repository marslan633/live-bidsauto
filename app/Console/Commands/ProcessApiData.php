<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessApiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:api-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and process data from third-party API and save it into the database';

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     // Log the starting time of the process
    //     $startTime = microtime(true);
    //     $startDateTime = Carbon::now();
    //     $this->info("Process started at: " . $startDateTime);
    //     \Log::info("Process started at: " . $startDateTime);

    //     // Create an entry in the cron_run_history table
    //     $cronRun = DB::table('cron_run_history')->insertGetId([
    //         'cron_name' => 'process_vehicle_data',
    //         'start_time' => $startDateTime,
    //         'status' => 'running',
    //         'created_at' => now(),
    //         'updated_at' => now(),
    //     ]);

    //     // Starting URL for the first page
    //     // $apiUrl = 'http://carstat.dev/api/cars?minutes=4320&page=1&per_page=1000';

    //     $apiUrl = 'http://carstat.dev/api/cars?minutes=500&page=1&per_page=500';
    //     try {
    //         do {
    //             // Fetch data from the API
    //             $response = Http::withHeaders([
    //                 'x-api-key' => env('CAR_API_KEY'),
    //             ])
    //             ->timeout(120) // Timeout set to 120 seconds
    //             ->retry(3, 1000) // Retry 3 times with a 1-second delay
    //             ->get($apiUrl);

    //             $start = microtime(true);
    //             // Log response time for performance monitoring
    //             $end = microtime(true);
    //             \Log::info("API URL: $apiUrl, Response time: " . ($end - $start) . " seconds");

    //             // Log the API hit URL for debugging purposes
    //             $this->info("API URL: $apiUrl");
    //             \Log::info("API URL: $apiUrl");

    //             // Log the full response for debugging purposes
    //             $this->info("Response: " . $response->body());
    //             // \Log::info("Response: " . $response->body());

    //             if ($response->successful()) {
    //                 // Extract the data from the response
    //                 $data = $response->json()['data'];
                    
    //                 // Process each car's data
    //                 foreach ($data as $car) {
    //                     $this->processCarData($car);
    //                 }

    //                 // Log successful data processing
    //                 $this->info('Data processed successfully.');
    //                 \Log::info('Data processed successfully.');

    //                 // Check the 'next' link to fetch the next page
    //                 $nextUrl = $response->json()['links']['next'];

    //                 $lastUrl = $response->json()['links']['last'];
    //                 \Log::info("Last Page API URL: $lastUrl");
    //                 // If there is a next page, update $apiUrl to fetch the next page, otherwise end the loop
    //                 if ($nextUrl) {
    //                     $apiUrl = $nextUrl;
    //                 } else {
    //                     // Update cron_run_history with successful status
    //                     DB::table('cron_run_history')->where('id', $cronRun)->update([
    //                         'end_time' => Carbon::now(),
    //                         'status' => 'success',
    //                         'updated_at' => now(),
    //                     ]);
    //                     $this->info('No more pages to fetch.');
    //                     \Log::info('No more pages to fetch.');

    //                     // Call the second cron job when 'next' is null
    //                     $this->info('Triggering the second cron job: update:pivot-counts');
    //                     \Log::info('Triggering the second cron job: update:pivot-counts');
    //                     $this->call('update:pivot-counts');
    //                 }

    //             } else {
    //                 // Handle failed API request
    //                 $this->error('Failed to fetch API data.');
    //                 \Log::info('Failed to fetch API data.');
    //                 break; // Exit the loop if the API request fails
    //             }

    //         } while ($nextUrl !== null); // Continue fetching until 'next' is null
            
    //     } catch (\Exception $e) {
    //         // Log the exception and update the cron_run_history table with the error status
    //         $this->error("Error: " . $e->getMessage());
    //         \Log::error("Error: " . $e->getMessage());

    //         DB::table('cron_run_history')->where('id', $cronRun)->update([
    //             'end_time' => Carbon::now(),
    //             'status' => 'failed',
    //             'error_message' => $e->getMessage(),
    //             'updated_at' => now(),
    //         ]);
    //     }
        

    //     // Log the ending time of the process
    //     $endTime = microtime(true);
    //     $endDateTime = Carbon::now();
    //     $this->info("Process ended at: " . $endDateTime);
    //     \Log::info("Process ended at: " . $endDateTime);

    //     // Calculate the total execution time
    //     $executionTime = $endTime - $startTime; // In seconds, including fractions
    //     $formattedTime = round($executionTime, 2); // Round to 2 decimal places
    //     $this->info("Total execution time: {$formattedTime} seconds");
    //     \Log::info("Total execution time: {$formattedTime} seconds");

    //     // Final completion log
    //     $this->info('Data processing completed.');
    //     \Log::info('Data processing completed.');
    // }
    public function handle()
    {
        $startTime = microtime(true);
        $startDateTime = Carbon::now();
        $this->info("Process started at: " . $startDateTime);
        \Log::info("Process started at: " . $startDateTime);

        $cronRun = DB::table('cron_run_history')->insertGetId([
            'cron_name' => 'process_vehicle_data',
            'start_time' => $startDateTime,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $minutes = 15; // Time frame in minutes
        $perPage = 500; // Records per page
        $baseUrl = 'http://carstat.dev/api/cars';
        $totalPages = 0;

        try {
            // Fetch total records from the API
            $response = Http::withHeaders([
                'x-api-key' => env('CAR_API_KEY'),
            ])
            ->timeout(120)
            ->retry(3, 1000)
            ->get("{$baseUrl}?minutes={$minutes}&page=1&per_page={$perPage}");

            if ($response->successful()) {
                $totalRecords = $response->json()['meta']['total'] ?? 0;
                $this->info("Fetching total number of record: {$totalRecords}");
                \Log::info("Fetching total number of record: {$totalRecords}");

                if ($totalRecords > 0) {
                    $totalPages = ceil($totalRecords / $perPage); // Calculate total API calls required
                    $this->info("Total Pages: $totalPages");
                    \Log::info("Total Pages: $totalPages");
                    
                    for ($page = 1; $page <= $totalPages; $page++) {
                        $apiUrl = "{$baseUrl}?minutes={$minutes}&page={$page}&per_page={$perPage}";

                        $this->info("Fetching page {$page} of {$totalPages}");
                        \Log::info("Fetching page {$page} of {$totalPages}, URL: {$apiUrl}");

                        $pageResponse = Http::withHeaders([
                            'x-api-key' => env('CAR_API_KEY'),
                        ])
                        ->timeout(120)
                        ->retry(3, 1000)
                        ->get($apiUrl);
                        
                        $this->info("API URL: $apiUrl");
                        \Log::info("API URL: $apiUrl");

                        if ($pageResponse->successful()) {
                            $data = $pageResponse->json()['data'] ?? [];
                            
                            foreach ($data as $car) {
                                $this->processCarData($car);
                            }

                            $this->info("Page {$page} processed successfully.");
                            \Log::info("Page {$page} processed successfully.");
                        } else {
                            $this->error("Failed to fetch data for page {$page}.");
                            \Log::error("Failed to fetch data for page {$page}.");
                            break;
                        }
                    }
                } else {
                    $this->info("No records to process.");
                    \Log::info("No records to process.");
                }
            } else {
                $this->error("Failed to fetch total records.");
                \Log::error("Failed to fetch total records.");
            }

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'success',
                'updated_at' => now(),
            ]);
            $this->info('Data processing completed.');
            \Log::info('Data processing completed.');

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            \Log::error("Error: " . $e->getMessage());

            DB::table('cron_run_history')->where('id', $cronRun)->update([
                'end_time' => Carbon::now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);
        } 
        // finally {
        //     // Ensure the second cron job is always called
        //     $this->info('Triggering the second cron job: update:pivot-counts');
        //     \Log::info('Triggering the second cron job: update:pivot-counts');
        //     // $this->call('update:pivot-counts');
        // }

        $endTime = microtime(true);
        $endDateTime = Carbon::now();
        $this->info("Process ended at: " . $endDateTime);
        \Log::info("Process ended at: " . $endDateTime);

        $executionTime = $endTime - $startTime;
        $formattedTime = round($executionTime, 2);
        $this->info("Total execution time: {$formattedTime} seconds");
        \Log::info("Total execution time: {$formattedTime} seconds");
    }

    private function processCarData(array $car)
    {
        // Initialize variables as null
        $model = null;
        $generation = null;

        // Define placeholders for "Unknown"
        $unknownApiId = 0;
        $unknownName = 'Unknown';
        
        // Process Manufacturer
        $manufacturer = $car['manufacturer'] ? Manufacturer::firstOrCreate(
            ['manufacturer_api_id' => $car['manufacturer']['id']],
            ['name' => $car['manufacturer']['name']]
        ) : Manufacturer::firstOrCreate(
            ['manufacturer_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Model
        if ($manufacturer) {
            $model = $car['model'] ? VehicleModel::firstOrCreate(
                ['vehicle_model_api_id' => $car['model']['id']],
                [
                    'name' => $car['model']['name'],
                    'manufacturer_id' => $manufacturer->id
                ]
            ) : VehicleModel::firstOrCreate(
                ['vehicle_model_api_id' => $unknownApiId],
                [
                    'name' => $unknownName,
                    'manufacturer_id' => $manufacturer->id
                ]
            );
        }

        // Process Generation
        if ($manufacturer && $model) {
            $generation = $car['generation'] ? Generation::firstOrCreate(
                ['generation_api_id' => $car['generation']['id']],
                [
                    'name' => $car['generation']['name'],
                    'manufacturer_id' => $manufacturer->id,
                    'model_id' => $model->id
                ]
            ) : Generation::firstOrCreate(
                ['generation_api_id' => $unknownApiId],
                [
                    'name' => $unknownName,
                    'manufacturer_id' => $manufacturer->id,
                    'model_id' => $model->id
                ]
            );
        }

        // Process Year
        $year = null;
        if ($car['year']) {
            $year = Year::firstOrCreate(
                ['name' => $car['year']]
            );
        }
        
        // Process BodyType
        $bodyType = $car['body_type'] ? BodyType::firstOrCreate(
            ['body_type_api_id' => $car['body_type']['id']],
            [
                'name' => $car['body_type']['name'],
            ]
        ) : BodyType::firstOrCreate(
            ['body_type_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Color
        $color = $car['color'] ? Color::firstOrCreate(
            ['color_api_id' => $car['color']['id']],
            ['name' => $car['color']['name']]
        ) : Color::firstOrCreate(
            ['color_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Engine
        $engine = $car['engine'] ? Engine::firstOrCreate(
            ['engine_api_id' => $car['engine']['id']],
            ['name' => $car['engine']['name']]
        ) : Engine::firstOrCreate(
            ['engine_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Transmission
        $transmission = $car['transmission'] ? Transmission::firstOrCreate(
            ['transmission_api_id' => $car['transmission']['id']],
            ['name' => $car['transmission']['name']]
        ) : Transmission::firstOrCreate(
            ['transmission_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Drive Wheel
        $driveWheel = $car['drive_wheel'] ? DriveWheel::firstOrCreate(
            ['drive_wheel_api_id' => $car['drive_wheel']['id']],
            ['name' => $car['drive_wheel']['name']]
        ) : DriveWheel::firstOrCreate(
            ['drive_wheel_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Vehicle Type
        $vehicleType = $car['vehicle_type'] ? VehicleType::firstOrCreate(
            ['vehicle_type_api_id' => $car['vehicle_type']['id']],
            ['name' => $car['vehicle_type']['name']]
        ) : VehicleType::firstOrCreate(
            ['vehicle_type_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Fuel
        $fuel = $car['fuel'] ? Fuel::firstOrCreate(
            ['fuel_api_id' => $car['fuel']['id']],
            ['name' => $car['fuel']['name']]
        ) : Fuel::firstOrCreate(
            ['fuel_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Vehicle Record
        $vehicleRecord = VehicleRecord::updateOrCreate(
            ['api_id' => $car['id']],
            [
                'year' => $car['year'],
                'year_id' => $year?->id,
                'title' => $car['title'],
                'vin' => $car['vin'],
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
                'cylinders' => $car['cylinders'],
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
        foreach ($car['lots'] as $lot) {
            $this->processLot($vehicleRecord, $lot);
        }
    }

    private function processLot($vehicleRecord, $lot)
    {
        $unknownApiId = 0;
        $unknownName = 'Unknown';
        // Determine buy_now_id based on buy_now value
        $buyNowValue = $lot['buy_now'] ?? null;
        $buyNowId = null;

        if ($buyNowValue === 0 || is_null($buyNowValue)) {
            $buyNowId = BuyNow::where('name', 'buyNowWithoutPrice')->value('id');
        } elseif (is_numeric($buyNowValue) && $buyNowValue > 0) {
            $buyNowId = BuyNow::where('name', 'buyNowWithPrice')->value('id');
        }

        // Process Seller
        $domain = $lot['domain'] ? Domain::firstOrCreate(
            ['domain_api_id' => $lot['domain']['id']],
            ['name' => $lot['domain']['name']]
        ) : null;

        // Process Selling Branch
        $sellingBranch = $lot['selling_branch'] ? SellingBranch::firstOrCreate(
            ['selling_branch_api_id' => $lot['selling_branch']['id']],
            [
                'name' => $lot['selling_branch']['name'],
                'link' => $lot['selling_branch']['link'],
                'number' => $lot['selling_branch']['number'],
                'domain_id' => $lot['selling_branch']['domain_id'],
            ]
        ) : null;

        // Process Odometer
        $odometer = $lot['odometer']['mi'] ? Odometer::firstOrCreate(
            ['name' => $lot['odometer']['mi']]
        ) : Odometer::firstOrCreate(
            ['name' => $unknownName]
        );


        // Process Seller
        $seller = $lot['seller'] ? Seller::firstOrCreate(
            ['seller_api_id' => $lot['seller']['id']],
            ['name' => $lot['seller']['name']]
        ) : Seller::firstOrCreate(
            ['seller_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Seller Type
        $sellerType = $lot['seller_type'] ? SellerType::firstOrCreate(
            ['seller_type_api_id' => $lot['seller_type']['id']],
            ['name' => $lot['seller_type']['name']]
        ) : SellerType::firstOrCreate(
            ['seller_type_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        $unknownConditionApiId = 100;
        // Process Condition
        $condition = $lot['condition'] ? Condition::firstOrCreate(
            ['condition_api_id' => $lot['condition']['id']],
            ['name' => $lot['condition']['name']]
        ) : Condition::firstOrCreate(
            ['condition_api_id' => $unknownConditionApiId],
            ['name' => 'Unknown']
        );

        // Process Status
        $status = $lot['status'] ? Status::firstOrCreate(
            ['status_api_id' => $lot['status']['id']],
            ['name' => $lot['status']['name']]
        ) : Status::firstOrCreate(
            ['status_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );

        // Process Title
        $title = $lot['title'] ? Title::firstOrCreate(
            ['title_api_id' => $lot['title']['id']],
            ['name' => $lot['title']['name']]
        ) : Title::firstOrCreate(
            ['title_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );
        // Process Detailed Title
        $detailedTitle = $lot['detailed_title'] ? DetailedTitle::firstOrCreate(
            ['detailed_title_api_id' => $lot['detailed_title']['id']],
            ['name' => $lot['detailed_title']['name']]
        ) : DetailedTitle::firstOrCreate(
            ['detailed_title_api_id' => $unknownApiId],
            ['name' => $unknownName]
        );


        // Process Damage
        $damageMain = $lot['damage']['main'] ? Damage::firstOrCreate(
            ['damage_api_id' => $lot['damage']['main']['id']],
            ['name' => $lot['damage']['main']['name']]
        ) : null;

        $damageSecond = $lot['damage']['second'] ? Damage::firstOrCreate(
            ['damage_api_id' => $lot['damage']['second']['id']],
            ['name' => $lot['damage']['second']['name']]
        ) : null;

        $location = $lot['location'];
        // Handle Country
        $country = $location['country'] ? Country::firstOrCreate(
            ['iso' => $location['country']['iso']],
            ['name' => $location['country']['name']]
        ) : null;

        // Initialize variables to null
        $state = null;
        $city = null;
        $locationRecord = null;

        // Only proceed if the state is not null or empty
        if (!empty($location['state'])) {
            // Handle State
            $state = State::firstOrCreate(
                ['state_api_id' => $location['state']['id']],
                [
                    'country_id' => $country?->id,
                    'code' => $location['state']['code'],
                    'name' => $location['state']['name']
                ]
            );

            // Handle City
            if (!empty($location['city'])) {
                $city = City::firstOrCreate(
                    ['city_api_id' => $location['city']['id']],
                    [
                        'state_id' => $state->id,
                        'name' => $location['city']['name']
                    ]
                );

                // Handle Location
                if (!empty($location['location']) && !empty($location['location']['id'])) {
                    $locationRecord = Location::firstOrCreate(
                        ['location_api_id' => $location['location']['id']],
                        [
                            'city_id' => $city->id,
                            'name' => trim($location['location']['name']) ?: 'Unnamed Location',
                            'latitude' => $location['latitude'] ?? null,
                            'longitude' => $location['longitude'] ?? null,
                            'postal_code' => trim($location['postal_code']) ?: null,
                            'is_offsite' => $location['is_offsite'] ?? false,
                            'raw' => $location['raw'] ?? '{}'
                        ]
                    );
                }
            }
        }


        // Process Images
        if (!empty($lot['images'])) {
            $imagesData = $lot['images'];

            $imageRecord = Image::updateOrCreate(
                ['image_api_id' => $imagesData['id']],
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
            'salvage_id' => $lot['id'] ?? null,
            'lot_id' => $lot['lot'] ?? null,
            'domain_id' => $domain?->id,
            'external_id' => $lot['external_id'] ?? null,
            'odometer_km' => $lot['odometer']['km'] ?? null,
            'odometer_mi' => $lot['odometer']['mi'] ?? null,
            'odometer_id' => $odometer?->id,
            'odometer_status' => $lot['odometer']['status']['name'] ?? null,
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
            'airbags' => $lot['airbags']['name'] ?? null,
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
}