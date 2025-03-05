<?php

use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, RemoteCacheKey,
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function sendResponse($status, $status_code, $message, $data, $code){
    return response()->json([
        'status'   => $status,
        'status_code'   => $status_code,
        'message'   => $message,
        'data'      => $data,
    ], $code);
}


function convertAndStoreDataToRedis(array $car)
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
    $convertedData['engine'] = !empty($car['engine']) && !empty((array) $car['engine'])
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
    $convertedData['transmission'] = !empty($car['transmission']) && !empty((array) $car['transmission'])
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
    $convertedData['drive_wheel'] = !empty($car['drive_wheel']) && !empty((array) $car['drive_wheel'])
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
    $convertedData['vehicle_type'] = !empty($car['vehicle_type']) && !empty((array) $car['vehicle_type'])
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
    $convertedData['fuel'] = !empty($car['fuel']) && !empty((array) $car['fuel'])
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
    $convertedData['lot'] = $car['lots'][0];
    $lot = $convertedData['lots'][0];

    $processLotData = processLotData($lot);
    $convertedData['vehicle_record'] = array_merge($convertedData['vehicle_record'], $processLotData);

    $convertedData['vehicle_record']['domain'] = isset($car['lots'][0]['domain'])
    ?
        [
            'domain_api_id' => $car['lots'][0]['domain']['id'],
            'name' => $car['lots'][0]['domain']['name']
        ]
    :
        null;


    // Process Selling Branch
    $convertedData['vehicle_record']['selling_branch'] = isset($car['lots'][0]['selling_branch'])
    ?
        [
            'selling_branch_api_id' => $car['lots'][0]['selling_branch']['id'],
            'name' => $car['lots'][0]['selling_branch']['name'],
            'link' => $car['lots'][0]['selling_branch']['link'],
            'number' => $car['lots'][0]['selling_branch']['number'],
            'domain_id' => $car['lots'][0]['selling_branch']['domain_id']
        ]
    :
        null;

        // Process Title
        $convertedData['vehicle_record']['title_title'] = !empty($car['lots'][0]['title']) && !empty((array) $car['lots'][0]['title'])
        ?
            [
                'title_api_id' => $car['lots'][0]['title']['id'],
                'name' => $car['lots'][0]['title']['name']
            ]
        :
            [
                'title_api_id' => $unknownApiId,
                'name' => $unknownName
            ];

    return $convertedData;
}


function processLotData($lot)
{
    $unknownApiId = 0;
    $unknownName = 'unknown';
    // Determine buy_now_id based on buy_now value
    $buyNowValue = $lot['buy_now'] ?? null;

    $lotConveredData = [];


    if ($buyNowValue === 0 || is_null($buyNowValue)) {
        $lotConveredData['buy_now']  = 'buyNowWithoutPrice';
    } elseif (is_numeric($buyNowValue) && $buyNowValue > 0) {
        $lotConveredData['buy_now']  = 'buyNowWithPrice';
    }


    // $lotConveredData['new_domain'] = isset($lot['domain'])
    // ?
    //     [
    //         'domain_api_id' => $lot['domain']['id'],
    //         'name' => $lot['domain']['name']
    //     ]
    // :
    //     null;


    // Process Selling Branch
    // $lotConveredData['new_selling_branch'] = isset($lot['selling_branch'])
    // ?
    //     [
    //         'selling_branch_api_id' => $lot['selling_branch']['id'],
    //         'name' => $lot['selling_branch']['name'],
    //         'link' => $lot['selling_branch']['link'],
    //         'number' => $lot['selling_branch']['number'],
    //         'domain_id' => $lot['selling_branch']['domain_id']
    //     ]
    // :
    //     null;


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
    $lotConvertedData['seller'] = !empty($lot['seller']) && !empty((array) $lot['seller'])
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
    $lotConvertedData['seller_type'] = !empty($lot['seller_type']) && !empty((array) $lot['seller_type'])
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
    $lotConvertedData['condition'] = !empty($lot['condition']) && !empty((array) $lot['condition'])
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
    $lotConvertedData['status'] = !empty($lot['status']) && !empty((array) $lot['status'])
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



    // Process Detailed Title
    $lotConvertedData['detailed_title'] = !empty($lot['detailed_title']) && !empty((array) $lot['detailed_title'])
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
    $lotConvertedData['damageMain'] = isset($lot['damage']['main']) && !empty($lot['damage']['main'])
    ?
        [
            'damage_api_id' => $lot['damage']['main']['id'],
            'name' => $lot['damage']['main']['name']
        ]
    :
        null;

    $lotConvertedData['damageSecond'] = isset($lot['damage']['second']) && !empty($lot['damage']['second'])
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
                'code' => $location['state']['code'],
                'name' => $location['state']['name']
        ];
        // Handle City
        if (!empty($location['city'])) {
            $lotConvertedData['city'] = [
                'city_api_id' => $location['city']['id'],
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

function prepareCarDataOld(array $car)
{
    $car['vehicle_record'] = (array) ($car['vehicle_record'] ?? []);
    $imageRecord = $car['vehicle_record']['imageRecord'] ?? [];

    // Helper function to get or create record ID
    $getId = function ($class, $apiKey, $nameKey) use ($car) {
        return isset($car[$apiKey]) ? $class::firstOrCreate(
            [$apiKey . '_api_id' => $car[$apiKey][$apiKey . '_api_id']],
            ['name' => $car[$apiKey][$nameKey]]
        )->id : null;
    };

    $yearId = !empty($car['year']) ? Year::firstOrCreate(['name' => $car['year']])->id : null;

    // Vehicle Model
    $modelId = VehicleModel::firstOrCreate(
        ['vehicle_model_api_id' => $car['model']['vehicle_model_api_id']],
        ['name' => $car['model']['name']]
    )->id;

    // Image Processing
    $imageId = isset($imageRecord['image_api_id']) ? Image::updateOrCreate(
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
    )->id : null;

    return [
        'manufacturer_id' => $getId(Manufacturer::class, 'manufacturer', 'name'),
        'vehicle_model_id' => $modelId,
        'generation_id' => Generation::firstOrCreate(
            ['generation_api_id' => $car['generation']['generation_api_id']],
            ['name' => $car['generation']['name'], 'model_id' => $modelId]
        )->id,
        'body_type_id' => $getId(BodyType::class, 'body_type', 'name'),
        'color_id' => $getId(Color::class, 'color', 'name'),
        'engine_id' => $getId(Engine::class, 'engine', 'name'),
        'transmission_id' => $getId(Transmission::class, 'transmission', 'name'),
        'drive_wheel_id' => $getId(DriveWheel::class, 'drive_wheel', 'name'),
        'vehicle_type_id' => $getId(VehicleType::class, 'vehicle_type', 'name'),
        'fuel_id' => $getId(Fuel::class, 'fuel', 'name'),
        'year_id' => $yearId,
        'api_id' => $car['vehicle_record']['api_id'] ?? null,
        'title' => $car['vehicle_record']['title'] ?? null,
        'vin' => $car['vehicle_record']['vin'] ?? null,
        'cylinders' => $car['vehicle_record']['cylinders'] ?? null,
        'salvage_id' => $car['vehicle_record']['salvage_id'] ?? null,
        'lot_id' => $car['vehicle_record']['lot_id'] ?? null,
        'domain_id' => $getId(Domain::class, 'domain', 'name'),
        'selling_branch' => $getId(SellingBranch::class, 'selling_branch', 'name'),
        'external_id' => $car['vehicle_record']['external_id'] ?? null,
        'odometer_km' => $car['vehicle_record']['odometer_km'] ?? null,
        'odometer_mi' => $car['vehicle_record']['odometer_mi'] ?? null,
        'estimate_repair_price' => $car['vehicle_record']['estimate_repair_price'] ?? null,
        'pre_accident_price' => $car['vehicle_record']['pre_accident_price'] ?? null,
        'actual_cash_value' => $car['vehicle_record']['actual_cash_value'] ?? null,
        'sale_date' => $car['vehicle_record']['sale_date'] ?? null,
        'bid' => $car['vehicle_record']['bid'] ?? null,
        'buy_now' => $car['vehicle_record']['buy_now'] ?? null,
        'final_bid' => $car['vehicle_record']['final_bid'] ?? null,
        'keys_available' => $car['vehicle_record']['keys_available'] ?? null,
        'airbags' => $car['vehicle_record']['airbags'] ?? null,
        'grade_iaai' => $car['vehicle_record']['grade_iaai'] ?? null,
        'odometer_id' => $getId(Odometer::class, 'odometer', 'name'),
        'seller_id' => $getId(Seller::class, 'seller', 'name'),
        'seller_type_id' => $getId(SellerType::class, 'seller_type', 'name'),
        'condition_id' => $getId(Condition::class, 'condition', 'name'),
        'status_id' => $getId(Status::class, 'status', 'name'),
        'title_id' => $getId(Title::class, 'title_title', 'name'),
        'detailed_title_id' => $getId(DetailedTitle::class, 'detailed_title', 'name'),
        'damage_id' => $getId(Damage::class, 'damageMain', 'name'),
        'damage_main' => $getId(Damage::class, 'damageMain', 'name'),
        'damage_second' => $getId(Damage::class, 'damageSecond', 'name'),
        'buy_now_id' => BuyNow::where('name', $car['vehicle_record']['buy_now'] ?? '')->value('id'),
        'details' => $car['vehicle_record']['details'] ?? null,
        'location_id' => isset($car['vehicle_record']['locationRecord']['location_api_id']) ? Location::firstOrCreate(
            ['location_api_id' => $car['vehicle_record']['locationRecord']['location_api_id']],
            ['name' => $car['vehicle_record']['locationRecord']['name'] ?? 'Unnamed Location']
        )->id : null,
        'image_id' => $imageId,
    ];
}


 function prepareCarData(array $car)
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
function insertBatch(array $batchData)
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
        // $this->info("Inserted " . count($newRecords) . " new records.");
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
        // $this->info("Updated " . count($updatedRecords) . " existing records.");
    }
}
