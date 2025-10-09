<?php

use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, RemoteCacheKey,
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    $lotConvertedData = [];
    $lotConvertedData['buy_now'] = $lot['buy_now'] ?? null;

    if ($buyNowValue == 0 || is_null($buyNowValue)) {
        $lotConvertedData['buy_now_db']  = 2; // buyNowWithoutPrice;
    } elseif (is_numeric($buyNowValue) && $buyNowValue > 0) {
        $lotConvertedData['buy_now_db']  = 1; // buyNowWithPrice
    }
    // Log::info('ProcessLotData', ['data' => $lotConvertedData['buy_now']]);
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

    // Auction Type
    $lotConvertedData['auction_type'] = !empty($lot['auction_type']) && !empty((array) $lot['auction_type'])
    ?
        [
            'auction_type_api_id' => $lot['auction_type']['id'],
            'name' => $lot['auction_type']['name']
        ]
    :
        [
            'auction_type_api_id' => $unknownApiId,
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
    // Log::info('Pre Accident Price', ['pre_accident_price' => $lot['pre_accident_price'] ?? null]);

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
    $lotConvertedData['buy_now_updated_at'] = $lot['buy_now_updated_at'] ?? null;
    $lotConvertedData['final_bid'] = $lot['final_bid'] ?? null;
    $lotConvertedData['final_bid_updated_at'] = $lot['final_bid_updated_at'] ?? null;
    $lotConvertedData['keys_available'] = $lot['keys_available'] ?? null;
    $lotConvertedData['airbags'] = $lot['airbags']['name'] ?? null;
    $lotConvertedData['grade_iaai'] = $lot['grade_iaai'] ?? null;
    $lotConvertedData['details'] = $lot['details'] ?? null;
    $lotConvertedData['line'] = $lot['line'] ?? null;
    $lotConvertedData['tags'] = !empty($lot['tags']) && is_array($lot['tags'])
    ? implode(',', array_filter($lot['tags']))
    : null;
    $lotConveredData['is_timed_auction'] = $lot['is_timed_auction'];
    $lotConveredData['seller_reserve'] = $lot['seller_reserve']['price'] ?? null;
    return $lotConvertedData;

}


    function compressJson(array $data): string
    {
        return gzcompress(json_encode($data));
    }


    function decompressJson(?string $compressedData): ?array
    {
        if (!$compressedData) {
            return null;
        }

        $decompressed = gzuncompress($compressedData);

        return $decompressed ? json_decode($decompressed, true) : [];
    }


function compressData($data){
    $compressedData = gzencode(json_encode($data), 9);
    $base64Encoded = base64_encode($compressedData);
    return $base64Encoded;
}

function unCompressData($data){
    $decoded = base64_decode($data);
    $decompressed = gzdecode($decoded);
    return json_decode($decompressed, true);
}