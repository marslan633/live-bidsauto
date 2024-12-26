<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch
};

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
    public function handle()
    {
        $apiUrl = 'https://carstat.dev/api/cars?minutes=120&per_page=5';

        $response = Http::withHeaders([
                'x-api-key' => env('CAR_API_KEY'),
            ])->get($apiUrl);

        $this->info($response);
        \Log::info($response);   

        if ($response->successful()) {
            $data = $response->json()['data'];

            foreach ($data as $car) {
                $this->processCarData($car);
            }

            $this->info('Data processed successfully.');
            \Log::info('Data processed successfully.');
        } else {
            $this->error('Failed to fetch API data.');
            \Log::info('Failed to fetch API data.');
        }
    }

    private function processCarData(array $car)
    {
        // Process Manufacturer
        $manufacturer = Manufacturer::firstOrCreate(
            ['manufacturer_api_id' => $car['manufacturer']['id']],
            ['name' => $car['manufacturer']['name']]
        );

        // Process Model
        $model = VehicleModel::firstOrCreate(
            ['vehicle_model_api_id' => $car['model']['id']],
            [
                'name' => $car['model']['name'],
                'manufacturer_id' => $manufacturer->id
            ]
        );

        // Process Generation
        $generation = $car['generation'] ? Generation::firstOrCreate(
            ['generation_api_id' => $car['generation']['id']],
            [
                'name' => $car['generation']['name'],
                'manufacturer_id' => $manufacturer->id,
                'model_id' => $model->id
            ]
        ) : null;

        // Process BodyType
        $bodyType = $car['body_type'] ? BodyType::firstOrCreate(
            ['body_type_api_id' => $car['body_type']['id']],
            [
                'name' => $car['body_type']['name'],
            ]
        ) : null;

        // Process Color
        $color = $car['color'] ? Color::firstOrCreate(
            ['color_api_id' => $car['color']['id']],
            ['name' => $car['color']['name']]
        ) : null;

        // Process Engine
        $engine = $car['engine'] ? Engine::firstOrCreate(
            ['engine_api_id' => $car['engine']['id']],
            ['name' => $car['engine']['name']]
        ) : null;

        // Process Transmission
        $transmission = $car['transmission'] ? Transmission::firstOrCreate(
            ['transmission_api_id' => $car['transmission']['id']],
            ['name' => $car['transmission']['name']]
        ) : null;

        // Process Drive Wheel
        $driveWheel = $car['drive_wheel'] ? DriveWheel::firstOrCreate(
            ['drive_wheel_api_id' => $car['drive_wheel']['id']],
            ['name' => $car['drive_wheel']['name']]
        ) : null;

        // Process Vehicle Type
        $vehicleType = $car['vehicle_type'] ? VehicleType::firstOrCreate(
            ['vehicle_type_api_id' => $car['vehicle_type']['id']],
            ['name' => $car['vehicle_type']['name']]
        ) : null;

        // Process Fuel
        $fuel = $car['fuel'] ? Fuel::firstOrCreate(
            ['fuel_api_id' => $car['fuel']['id']],
            ['name' => $car['fuel']['name']]
        ) : null;

        // Process Vehicle Record
        $vehicleRecord = VehicleRecord::updateOrCreate(
            ['api_id' => $car['id']],
            [
                'year' => $car['year'],
                'title' => $car['title'],
                'vin' => $car['vin'],
                'manufacturer_id' => $manufacturer->id,
                'vehicle_model_id' => $model->id,
                'generation_id' => $generation?->id,
                'body_type_id' => $bodyType?->id,
                'color_id' => $color?->id,
                'engine_id' => $engine?->id,
                'transmission_id' => $transmission?->id,
                'drive_wheel_id' => $driveWheel?->id,
                'vehicle_type_id' => $vehicleType?->id,
                'fuel_id' => $fuel?->id,
                'cylinders' => $car['cylinders']
            ]
        );

        // Process lots
        foreach ($car['lots'] as $lot) {
            $this->processLot($vehicleRecord, $lot);
        }
    }

    private function processLot($vehicleRecord, $lot)
    {
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


        // Process Seller
        $seller = $lot['seller'] ? Seller::firstOrCreate(
            ['seller_api_id' => $lot['seller']['id']],
            ['name' => $lot['seller']['name']]
        ) : null;

        // Process Seller Type
        $sellerType = $lot['seller_type'] ? SellerType::firstOrCreate(
            ['seller_type_api_id' => $lot['seller_type']['id']],
            ['name' => $lot['seller_type']['name']]
        ) : null;

        // Process Condition
        $condition = $lot['condition'] ? Condition::firstOrCreate(
            ['condition_api_id' => $lot['condition']['id']],
            ['name' => $lot['condition']['name']]
        ) : null;

        // Process Status
        $status = $lot['status'] ? Status::firstOrCreate(
            ['status_api_id' => $lot['status']['id']],
            ['name' => $lot['status']['name']]
        ) : null;

        // Process Title
        $title = $lot['title'] ? Title::firstOrCreate(
            ['title_api_id' => $lot['title']['id']],
            ['name' => $lot['title']['name']]
        ) : null;

        // Process Detailed Title
        $detailedTitle = $lot['detailed_title'] ? DetailedTitle::firstOrCreate(
            ['detailed_title_api_id' => $lot['detailed_title']['id']],
            ['name' => $lot['detailed_title']['name']]
        ) : null;


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

        // Handle State
        $state = $location['state'] ? State::firstOrCreate(
            ['state_api_id' => $location['state']['id']],
            ['country_id' => $country->id, 'code' => $location['state']['code'], 'name' => $location['state']['name']]
        ) : null;

        // Handle City
        $city = $location['city'] ? City::firstOrCreate(
            ['city_api_id' => $location['city']['id']],
            ['state_id' => $state->id, 'name' => $location['city']['name']]
        ) : null;

        // Handle Location
        $locationRecord = $location['location'] ? Location::firstOrCreate(
            ['location_api_id' => $location['location']['id']],
            [
                'city_id' => $city->id,
                'name' => $location['location']['name'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'postal_code' => $location['postal_code'],
                'is_offsite' => $location['is_offsite'],
                'raw' => $location['raw']
            ]
        ) : null;

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
                    'exterior' => $imagesData['exterior'] ?? null,
                    'interior' => $imagesData['interior'] ?? null,
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
        ]);
    }
}