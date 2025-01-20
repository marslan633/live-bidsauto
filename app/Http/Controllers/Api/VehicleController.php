<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{VehicleRecord, VehicleType, Domain, Manufacturer, VehicleModel, Condition, Fuel, SellerType, DriveWheel, Transmission, DetailedTitle, Damage, Year, BuyNow};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendQuoteMail;

class VehicleController extends Controller
{
    public function vehicleInformations(Request $request)
    {
        try {
            $query = VehicleRecord::with([
                'manufacturer', 'vehicleModel', 'generation', 'bodyType', 'color', 'engine', 
                'transmission', 'driveWheel', 'vehicleType', 'fuel', 'status', 'seller', 
                'sellerType', 'title', 'detailedTitle', 'damageMain', 'damageSecond', 
                'condition', 'image', 'country', 'state', 'city', 'location', 'sellingBranch'
            ]);
            
            $query->whereNotNull('sale_date');
            // ->where('is_new', false);

            // Handling 'Domain'
            if ($request->has('domain_id')) {
                $query->where('domain_id', $request->input('domain_id'));
            }

            // Handling 'bid_amount' sorting
            if ($request->has('bid_amount')) {
                $order = $request->input('bid_amount') === 'highest' ? 'DESC' : 'ASC';
                $query->orderBy('bid', $order);
            }

            // Handling 'buy_now_sort' filter
            if ($request->has('buy_now_sort')) {
                $order = $request->input('buy_now_sort') == true ? 'DESC' : 'ASC';
                $query->orderBy('buy_now', $order);
            }

            // Get the current date
            $currentDate = \Carbon\Carbon::now()->toDateString();

            // Determine the sorting order for sale date
            $saleDateOrder = $request->input('sale_date_order', 'sooner');

            if ($saleDateOrder === 'farthest') {
                // Focus on future sale dates (on or after today) and sort them from latest to earliest
                $query->orderByRaw('DATE(sale_date) >= ? DESC', [$currentDate])
                    ->orderBy('sale_date', 'desc');
            } else {
                // Focus on future sale dates (on or after today) and sort them from earliest to latest
                $query->orderByRaw('DATE(sale_date) >= ? DESC', [$currentDate])
                    ->orderBy('sale_date', 'asc');
            }

            // Handle the 'buy_now'
            if ($request->has('buy_now')) {
                if ($request->buy_now == true) {  
                    $buy_now_id = BuyNow::where('name', 'buyNowWithPrice')->pluck('id');
                    $query->where('buy_now_id', $buy_now_id);
                } elseif ($request->buy_now == false) {
                    $buy_now_ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
                            ->pluck('id')
                            ->toArray();
                        $query->whereIn('buy_now_id', $buy_now_ids);
                }
            }

            // Handling 'year_from' and 'year_to'
            if ($request->has('year_from') && $request->has('year_to')) {
                $query->whereBetween('year', [(int) $request->input('year_from'), (int) $request->input('year_to')]);
            }

            // Handling 'odometer_min' and 'odometer_max'
            if ($request->has('odometer_min') && $request->has('odometer_max')) {
                // Remove commas and cast to integers
                $odometerMin = (int) str_replace(',', '', $request->input('odometer_min'));
                $odometerMax = (int) str_replace(',', '', $request->input('odometer_max'));
                
                // Perform query filtering
                $query->whereBetween('odometer_mi', [$odometerMin, $odometerMax]);
            }

            // Handling 'auction_date' and 'auction_date_from' and 'auction_date_to
            if ($request->has('auction_date')) {
                $auctionDateInput = $request->input('auction_date');

                // Check if it's a date range (contains "to")
                if (strpos($auctionDateInput, 'to') !== false) {
                    [$auctionDateFrom, $auctionDateTo] = explode(' to ', $auctionDateInput);
                    $auctionDateFrom = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom))->startOfDay();
                    $auctionDateTo = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateTo))->endOfDay();

                    $query->whereBetween('sale_date', [$auctionDateFrom, $auctionDateTo]);
                } else {
                    // Single date case
                    $auctionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $auctionDateInput)->startOfDay();
                    $query->whereDate('sale_date', $auctionDate);
                }
            }

            // Define filters with their corresponding column names
            $filters = [
                'manufacturers' => 'manufacturer_id',
                'vehicle_models' => 'vehicle_model_id',
                'vehicle_types' => 'vehicle_type_id',
                'conditions' => 'condition_id',
                'fuels' => 'fuel_id',
                'seller_types' => 'seller_type_id',
                'drive_wheels' => 'drive_wheel_id',
                'transmissions' => 'transmission_id',
                'detailed_titles' => 'detailed_title_id',
                'damages' => 'damage_id'
            ];

            // Apply filters dynamically
            foreach ($filters as $requestKey => $dbColumn) {
                if ($request->has($requestKey) && is_array($request->input($requestKey))) {
                    $query->whereIn($dbColumn, $request->input($requestKey));
                }
            }
        
            // Pagination
            $page = $request->input('page', 1);
            $size = $request->input('size', 10);

            $totalCount = $query->count();
            $vehicleInformations = $query->skip(($page - 1) * $size)->take($size)->get();

            $response = [
                'count' => $totalCount,
                'data' => $vehicleInformations
            ];

            return sendResponse(true, 200, 'Vehicle Informations Fetched Successfully!', $response, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    /**
    * Search vehicle information records throught lot_id or vin.
    */    
    public function searchVehicle(Request $request, $id) {
        try {
            $query = VehicleRecord::with([
                'manufacturer', 'vehicleModel', 'generation', 'bodyType', 'color', 'engine', 
                'transmission', 'driveWheel', 'vehicleType', 'fuel', 'status', 'seller', 
                'sellerType', 'title', 'detailedTitle', 'damageMain', 'damageSecond', 
                'condition', 'image', 'country', 'state', 'city', 'location', 'sellingBranch'
            ]);
            
            if ($request->type == 'lot_id') {
                $query->where('lot_id', $id);
            } elseif ($request->type == 'vin') {
                $query->where('vin', $id);
            } else {
                return sendResponse(false, 400, 'Bad Request', 'Invalid search type specified', 200);
            }

            $record = $query->first();

            if ($record) {
                return sendResponse(true, 200, 'Car Detail Fetched Successfully!', $record, 200);
            } else {
                return sendResponse(false, 404, 'Not Found', 'Car detail not found', 200);
            }
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }
    
    /**
    * Fetch Side Filter Vehicle Attribute on the base of domain ID.
    */
    public function getRelatedAttributes(Request $request)
    {
        try {
            $domainId = $request->input('domain_id');

            if (!$domainId) {
                return sendResponse(false, 400, 'Domain ID is required.', null, 200);
            }

            $domain = Domain::find($domainId);
            if (!$domain) {
                return sendResponse(false, 404, 'Domain not found.', null, 200);
            }

            // Fetch related models with their counts
            $attributes = [
                'manufacturers' => $domain->manufacturers->map(function ($manufacturer) {
                    return [
                        'id' => $manufacturer->id,
                        'name' => $manufacturer->name,
                        'count' => $manufacturer->pivot->count ?? 0,
                    ];
                }),
                'vehicle_models' => $domain->vehicle_models->map(function ($model) {
                    return [
                        'id' => $model->id,
                        'name' => $model->name,
                        'count' => $model->pivot->count ?? 0,
                    ];
                }),
                'vehicle_types' => $domain->vehicle_types->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'count' => $type->pivot->count ?? 0,
                    ];
                }),
                'conditions' => $domain->conditions->map(function ($condition) {
                    return [
                        'id' => $condition->id,
                        'name' => $condition->name,
                        'count' => $condition->pivot->count ?? 0,
                    ];
                }),
                'fuels' => $domain->fuels->map(function ($fuel) {
                    return [
                        'id' => $fuel->id,
                        'name' => $fuel->name,
                        'count' => $fuel->pivot->count ?? 0,
                    ];
                }),
                'seller_types' => $domain->seller_types->map(function ($sellerType) {
                    return [
                        'id' => $sellerType->id,
                        'name' => $sellerType->name,
                        'count' => $sellerType->pivot->count ?? 0,
                    ];
                }),
                'drive_wheels' => $domain->drive_wheels->map(function ($driveWheel) {
                    return [
                        'id' => $driveWheel->id,
                        'name' => $driveWheel->name,
                        'count' => $driveWheel->pivot->count ?? 0,
                    ];
                }),
                'transmissions' => $domain->transmissions->map(function ($transmission) {
                    return [
                        'id' => $transmission->id,
                        'name' => $transmission->name,
                        'count' => $transmission->pivot->count ?? 0,
                    ];
                }),
                'detailed_titles' => $domain->detailed_titles->map(function ($title) {
                    return [
                        'id' => $title->id,
                        'name' => $title->name,
                        'count' => $title->pivot->count ?? 0,
                    ];
                }),
                'damages' => $domain->damages->map(function ($damage) {
                    return [
                        'id' => $damage->id,
                        'name' => $damage->name,
                        'count' => $damage->pivot->count ?? 0,
                    ];
                }),
                'years' => $domain->years->map(function ($year) {
                    return [
                        'id' => $year->id,
                        'name' => $year->name,
                        'count' => $year->pivot->count ?? 0,
                    ];
                }),
                'buy_nows' => $domain->buyNows->map(function ($buyNow) {
                    return [
                        'id' => $buyNow->id,
                        'name' => $buyNow->name,
                        'count' => $buyNow->pivot->count ?? 0,
                    ];
                }),
            ];

            return sendResponse(true, 200, 'Vehicle attribute fetched successfully!', $attributes, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error.', $ex->getMessage(), 500);
        }
    }

    // public function filterAttributes(Request $request, $attribute)
    // {
    //     // $request->validate([
    //     //     'domain_id' => 'required|integer',
    //     //     'manufacturers' => 'required|array',
    //     //     'manufacturers.*' => 'integer',
    //     // ]);

    //     $domainId = $request->input('domain_id');
    //     $manufacturerIds = $request->input('manufacturers');
    //     $vehicleModelIds = $request->input('vehicle_models');
        
    //     try {
    //         if ($attribute == 'manufacturers') {
    //             $manufacturers = Manufacturer::with([
    //                 'vehicleModels' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'vehicleTypes' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'conditions' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'fuels' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'sellerTypes' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'driveWheels' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'transmissions' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'detailedTitles' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'damages' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'years' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'buyNows' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //             ])->whereIn('id', $manufacturerIds)->get();

    //             // Prepare result grouped by attribute types
    //             $attributes = [
    //                 'vehicle_models' => [],
    //                 'vehicle_types' => [],
    //                 'conditions' => [],
    //                 'fuels' => [],
    //                 'seller_types' => [],
    //                 'drive_wheels' => [],
    //                 'transmissions' => [],
    //                 'detailed_titles' => [],
    //                 'damages' => [],
    //                 'years' => [],
    //                 'buy_nows' => [],
    //             ];

    //             foreach ($manufacturers as $manufacturer) {
    //                 foreach ($attributes as $key => &$attributeGroup) {
    //                     $relationName = Str::camel($key);
    //                     if ($manufacturer->$relationName) {
    //                         foreach ($manufacturer->$relationName as $relatedItem) {
    //                             $existingIndex = array_search($relatedItem->id, array_column($attributeGroup, 'id'));

    //                             if ($existingIndex !== false) {
    //                                 // If the item already exists, update the count
    //                                 $attributeGroup[$existingIndex]['count'] += $relatedItem->pivot->count ?? 0;
    //                             } else {
    //                                 // If the item is new, add it to the group
    //                                 $attributeGroup[] = [
    //                                     'id' => $relatedItem->id,
    //                                     'name' => $relatedItem->name,
    //                                     'count' => $relatedItem->pivot->count ?? 0,
    //                                 ];
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             return sendResponse(true, 200, 'Attributes fetched successfully.', $attributes, 200);
    //         }

    //         if ($attribute == 'vehicle_models') {
    //             $vehicle_models = VehicleModel::with([
    //                 'manufacturers' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'vehicleTypes' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'conditions' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'fuels' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'sellerTypes' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'driveWheels' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'transmissions' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'detailedTitles' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'damages' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'years' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //                 'buyNows' => function ($query) use ($domainId) {
    //                     $query->where('domain_id', $domainId);
    //                 },
    //             ])->whereIn('id', $vehicleModelIds)->get();
                
    //             // Prepare result grouped by attribute types
    //             $attributes = [
    //                 'manufacturers' => [],
    //                 'vehicle_types' => [],
    //                 'conditions' => [],
    //                 'fuels' => [],
    //                 'seller_types' => [],
    //                 'drive_wheels' => [],
    //                 'transmissions' => [],
    //                 'detailed_titles' => [],
    //                 'damages' => [],
    //                 'years' => [],
    //                 'buy_nows' => [],
    //             ];

    //             foreach ($vehicle_models as $vehicle_model) {
    //                 foreach ($attributes as $key => &$attributeGroup) {
    //                     $relationName = Str::camel($key);
    //                     if ($vehicle_model->$relationName) {
    //                         foreach ($vehicle_model->$relationName as $relatedItem) {
    //                             $existingIndex = array_search($relatedItem->id, array_column($attributeGroup, 'id'));

    //                             if ($existingIndex !== false) {
    //                                 // If the item already exists, update the count
    //                                 $attributeGroup[$existingIndex]['count'] += $relatedItem->pivot->count ?? 0;
    //                             } else {
    //                                 // If the item is new, add it to the group
    //                                 $attributeGroup[] = [
    //                                     'id' => $relatedItem->id,
    //                                     'name' => $relatedItem->name,
    //                                     'count' => $relatedItem->pivot->count ?? 0,
    //                                 ];
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             return sendResponse(true, 200, 'Attributes fetched successfully.', $attributes, 200);
    //         }
    //     } catch (\Exception $ex) {
    //         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
    //     }
    // }

    // public function filterAttributes(Request $request, $attribute)
    // {
    //     $domainId = $request->input('domain_id');
    //     $ids = $request->input($attribute);

    //     try {
    //         // Define model-to-relation mappings
    //         $relationMappings = [
    //             'manufacturers' => [
    //                 'model' => Manufacturer::class,
    //                 'relations' => [
    //                     'vehicleModels' => 'manufacturer_vehicle_model',
    //                     'vehicleTypes' => 'manufacturer_vehicle_type',
    //                     'conditions' => 'manufacturer_condition',
    //                     'fuels' => 'manufacturer_fuel',
    //                     'sellerTypes' => 'manufacturer_seller_type',
    //                     'driveWheels' => 'manufacturer_drive_wheel',
    //                     'transmissions' => 'manufacturer_transmission',
    //                     'detailedTitles' => 'manufacturer_detailed_title',
    //                     'damages' => 'manufacturer_damage',
    //                     'years' => 'manufacturer_year',
    //                     'buyNows' => 'manufacturer_buy_now',
    //                 ],
    //             ],
    //             'vehicle_models' => [
    //                 'model' => VehicleModel::class,
    //                 'relations' => [
    //                     'manufacturers' => 'vehicle_model_manufacturer',
    //                     'vehicleTypes' => 'vehicle_model_vehicle_type',
    //                     'conditions' => 'vehicle_model_condition',
    //                     'fuels' => 'vehicle_model_fuel',
    //                     'sellerTypes' => 'vehicle_model_seller_type',
    //                     'driveWheels' => 'vehicle_model_drive_wheel',
    //                     'transmissions' => 'vehicle_model_transmission',
    //                     'detailedTitles' => 'vehicle_model_detailed_title',
    //                     'damages' => 'vehicle_model_damage',
    //                     'years' => 'vehicle_model_year',
    //                     'buyNows' => 'vehicle_model_buy_now',
    //                 ],
    //             ],
    //             // 'vehicle_types' => [
    //             //     'model' => VehicleType::class,
    //             //     'relations' => [
    //             //         'manufacturers' => 'vehicle_type_manufacturer',
    //             //         'vehicleModels' => 'vehicle_type_vehicle_model',
    //             //         'conditions' => 'vehicle_type_condition',
    //             //         'fuels' => 'vehicle_type_fuel',
    //             //         'sellerTypes' => 'vehicle_type_seller_type',
    //             //         'driveWheels' => 'vehicle_type_drive_wheel',
    //             //         'transmissions' => 'vehicle_type_transmission',
    //             //         'detailedTitles' => 'vehicle_type_detailed_title',
    //             //         'damages' => 'vehicle_type_damage',
    //             //         'years' => 'vehicle_type_year',
    //             //         'buyNows' => 'vehicle_type_buy_now',
    //             //     ],
    //             // ],
    //             // 'conditions' => [
    //             //     'model' => Condition::class,
    //             //     'relations' => [
    //             //         'manufacturers' => 'condition_manufacturer',
    //             //         'vehicleModels' => 'condition_vehicle_model',
    //             //         'vehicleTypes' => 'condition_vehicle_type',
    //             //         'fuels' => 'condition_fuel',
    //             //         'sellerTypes' => 'condition_seller_type',
    //             //         'driveWheels' => 'condition_drive_wheel',
    //             //         'transmissions' => 'condition_transmission',
    //             //         'detailedTitles' => 'condition_detailed_title',
    //             //         'damages' => 'condition_damage',
    //             //         'years' => 'condition_year',
    //             //         'buyNows' => 'condition_buy_now',
    //             //     ],
    //             // ],
    //         ];

    //         if (!isset($relationMappings[$attribute])) {
    //             return sendResponse(false, 400, 'Invalid attribute type.', '', 200);
    //         }

    //         $modelClass = $relationMappings[$attribute]['model'];
    //         $relations = $relationMappings[$attribute]['relations'];
    //         $attributes = array_fill_keys(array_keys($relations), []);

    //         // Define constraints for filtering by domain_id
    //         $relationConstraints = [];
    //         foreach ($relations as $relationName => $pivotTable) {
    //             $relationConstraints[$relationName] = function ($query) use ($domainId) {
    //                 $query->where('domain_id', $domainId);
    //             };
    //         }

    //         // Fetch data with domain_id filtering applied
    //         $modelClass::with($relationConstraints)
    //             ->whereIn('id', $ids)
    //             ->chunk(1000, function ($items) use (&$attributes, $relations) {
    //                 foreach ($items as $item) {
    //                     foreach ($relations as $relationName => $pivotTable) {
    //                         $relation = $item->$relationName;

    //                         if ($relation) {
    //                             foreach ($relation as $relatedItem) {
    //                                 $existingIndex = !empty($attributes[$relationName]) 
    //                                     ? array_search($relatedItem->id, array_column($attributes[$relationName], 'id')) 
    //                                     : false;

    //                                 if ($existingIndex !== false) {
    //                                     $attributes[$relationName][$existingIndex]['count'] += $relatedItem->pivot->count ?? 0;
    //                                 } else {
    //                                     $attributes[$relationName][] = [
    //                                         'id' => $relatedItem->id,
    //                                         'name' => $relatedItem->name,
    //                                         'count' => $relatedItem->pivot->count ?? 0,
    //                                     ];
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 }
    //             });

    //         return sendResponse(true, 200, 'Attributes fetched successfully.', $attributes, 200);
    //     } catch (\Exception $ex) {
    //         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
    //     }
    // }

    // public function filterAttributes(Request $request, $attribute)
    // {
    //     $domainId = $request->input('domain_id');
    //     $ids = $request->input($attribute);
    //     try {
    //         // Define the attribute-to-body parameter mappings
    //         $attributeBodyMappings = [
    //             'manufacturers' => 'manufacturers',
    //             'vehicle_models' => 'vehicle_models',
    //             'vehicle_types' => 'vehicle_types',
    //             'conditions' => 'conditions',
    //             'fuels' => 'fuels',
    //             'seller_types' => 'seller_types',
    //             'drive_wheels' => 'drive_wheels',
    //             'transmissions' => 'transmissions',
    //             'detailed_titles' => 'detailed_titles',
    //             'damages' => 'damages',
    //             'years' => ['year_from', 'year_to'],
    //             'buy_now' => 'buy_now'
    //         ];

    //         // Check if the attribute exists in the mappings
    //         if (array_key_exists($attribute, $attributeBodyMappings)) {
    //             $requiredParams = $attributeBodyMappings[$attribute];

    //             // Handle 'years' separately since it requires two parameters
    //             if ($attribute === 'years') {
    //                 if (!$request->has($requiredParams[0]) || !$request->has($requiredParams[1])) {
    //                     return sendResponse(false, 400, 'Invalid request: Body parameters "year_from and year_to" are required when "years" is passed in the URL.', '', 200);
    //                 }
    //             } else {
    //                 // Check if the corresponding body parameter exists
    //                 if (!$request->has($requiredParams)) {
    //                     return sendResponse(false, 400, "Invalid request: Body parameter \"$requiredParams\" is required when \"$attribute\" is passed in the URL.", '', 200);
    //                 }
    //             }
    //         }
            
    //         // Handle year filtering when manufacturerIdsArray is given in request.
    //         $yearFrom = $request->input('year_from');
    //         $yearTo = $request->input('year_to');
    //         $buyNow = $request->buy_now; // To simplify condition checks

    //         // Only proceed if yearFrom, yearTo, and ids are present
    //         if ($yearFrom && $yearTo && isset($ids)) {
    //             // Fetch year IDs
    //             $yearIds = Year::whereBetween('name', [$yearFrom, $yearTo])->pluck('id')->toArray();
                
    //             // Combine both attribute mappings into one
    //             $attributeMappings = [
    //                 'manufacturers' => ['manufacturer_year', 'manufacturer_buy_now'],
    //                 'vehicle_models' => ['vehicle_model_year', 'vehicle_model_buy_now'],
    //                 'vehicle_types' => ['vehicle_type_year', 'vehicle_type_buy_now'],
    //                 'conditions' => ['condition_year', 'condition_buy_now'],
    //                 'fuels' => ['fuel_year', 'fuel_buy_now'],
    //                 'seller_types' => ['seller_type_year', 'seller_type_buy_now'],
    //                 'drive_wheels' => ['drive_wheel_year', 'drive_wheel_buy_now'],
    //                 'transmissions' => ['transmission_year', 'transmission_buy_now'],
    //                 'detailed_titles' => ['detailed_title_year', 'detailed_title_buy_now'],
    //                 'damages' => ['damage_year', 'damage_buy_now'],
    //                 'buy_now' => ['buy_now_year', 'buy_now_year'],
    //             ];

    //             if (isset($attributeMappings[$attribute])) {
    //                 // Get the table names
    //                 [$table, $tableBuyNow] = $attributeMappings[$attribute];

    //                 // Dynamically derive the column ID
    //                 $columnId = ($attribute === 'buy_now') ? 'buy_now_id' : rtrim($attribute, 's') . '_id';

    //                 if ($columnId === 'buy_now_id') {
    //                     if($request->buy_now == true) {
    //                         $ids = BuyNow::where('name', 'buyNowWithPrice')
    //                             ->pluck('id')
    //                             ->toArray();
    //                     } elseif ($request->buy_now == false) {
    //                         $ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
    //                             ->pluck('id')
    //                             ->toArray();
    //                     }
    //                 } 

    //                 // Filter by year range and current IDs
    //                 $ids = DB::table($table)
    //                     ->whereIn($columnId, $ids) // Filter by the current IDs
    //                     ->whereIn('year_id', $yearIds) // Filter by year range
    //                     ->pluck($columnId)
    //                     ->unique()
    //                     ->values()
    //                     ->toArray();

    //                 // Check and handle the 'buy_now' filtering if present in the request
    //                 if ($buyNow !== null) {
    //                     // Fetch BuyNow IDs based on the value of buy_now
    //                     $buyNowIds = $buyNow ? BuyNow::where('name', 'buyNowWithPrice')->pluck('id')->toArray() : 
    //                         BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])->pluck('id')->toArray();

    //                     // Fetch IDs based on buy_now filtering
    //                     $ids = DB::table($tableBuyNow)
    //                         ->whereIn($columnId, $ids)
    //                         ->whereIn('buy_now_id', $buyNowIds)
    //                         ->pluck($columnId)
    //                         ->unique()
    //                         ->values()
    //                         ->toArray();
    //                 }
    //             }
    //         }

    //         // Check if buy_now is set in the request and if $ids is not empty
    //         if (isset($buyNow) && $ids) {
    //             if ($request->buy_now == true) {
    //                 $buy_now_ids = BuyNow::where('name', 'buyNowWithPrice')->pluck('id')->toArray();
    //             } elseif ($request->buy_now == false) {
    //                 $buy_now_ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
    //                             ->pluck('id')
    //                             ->toArray();
    //             }
                        
                
    //             // Attribute to table mapping for 'buy_now' related filters
    //             $attributeBuyNowTableMapping = [
    //                 'manufacturers' => 'manufacturer_buy_now',
    //                 'vehicle_models' => 'vehicle_model_buy_now',
    //                 'vehicle_types' => 'vehicle_type_buy_now',
    //                 'conditions' => 'condition_buy_now',
    //                 'fuels' => 'fuel_buy_now',
    //                 'seller_types' => 'seller_type_buy_now',
    //                 'drive_wheels' => 'drive_wheel_buy_now',
    //                 'transmissions' => 'transmission_buy_now',
    //                 'detailed_titles' => 'detailed_title_buy_now',
    //                 'damages' => 'damage_buy_now',
    //             ];

    //             // Dynamically select the table based on the attribute
    //             if (isset($attributeBuyNowTableMapping[$attribute])) {
    //                 $tableBuyNow = $attributeBuyNowTableMapping[$attribute];
                    
    //                 // Dynamically derive the column ID based on the attribute (e.g., 'manufacturer_id' for 'manufacturers')
    //                 $columnId = ($attribute === 'buy_now') ? 'buy_now_id' : rtrim($attribute, 's') . '_id';
                    
    //                 // Fetch IDs based on the selected table, $ids, and the filtered $buy_now_ids
    //                 $attribute_buy_now = DB::table($tableBuyNow)
    //                     ->whereIn($columnId, $ids)               // Filter by the current IDs
    //                     ->whereIn('buy_now_id', $buy_now_ids)    // Filter by the 'buy_now' IDs
    //                     ->pluck($columnId)                       // Retrieve the column IDs
    //                     ->unique()                               // Ensure uniqueness
    //                     ->values()                               // Reindex the result array
    //                     ->toArray();

    //                 // Update $ids with the filtered results
    //                 $ids = $attribute_buy_now;
    //             }
    //         }

    //         // This below code should run only when years parameter will set.
    //         if($attribute == 'years') {
    //             // Extract year range from the request
    //             $yearFrom = $request->input('year_from');
    //             $yearTo = $request->input('year_to');
    //             $yearIds = [];

    //             if ($yearFrom && $yearTo) {
    //                 $ids = Year::whereBetween('name', [$yearFrom, $yearTo])->pluck('id')->toArray();
    //             }
    //         }
    //         if ($attribute === 'buy_now' && $request->has('buy_now')) {
    //             if ($request->buy_now == true && !($yearFrom && $yearTo)) {
    //                 $ids = BuyNow::where('name', 'buyNowWithPrice')
    //                     ->pluck('id')
    //                     ->toArray();
    //             } elseif ($request->buy_now == false) {
    //                 $ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
    //                     ->pluck('id')
    //                     ->toArray();
    //             }
    //         }

    //         // Define model-to-relation mappings
    //         $relationMappings = [
    //             'manufacturers' => [
    //                 'model' => Manufacturer::class,
    //                 'relations' => [
    //                     'vehicle_models' => 'manufacturer_vehicle_model',
    //                     'vehicle_types' => 'manufacturer_vehicle_type',
    //                     'conditions' => 'manufacturer_condition',
    //                     'fuels' => 'manufacturer_fuel',
    //                     'seller_types' => 'manufacturer_seller_type',
    //                     'drive_wheels' => 'manufacturer_drive_wheel',
    //                     'transmissions' => 'manufacturer_transmission',
    //                     'detailed_titles' => 'manufacturer_detailed_title',
    //                     'damages' => 'manufacturer_damage',
    //                     'years' => 'manufacturer_year',
    //                     'buyNows' => 'manufacturer_buy_now',
    //                 ],
    //             ],
    //             'vehicle_models' => [
    //                 'model' => VehicleModel::class,
    //                 'relations' => [
    //                     'manufacturers' => 'vehicle_model_manufacturer',
    //                     'vehicle_types' => 'vehicle_model_vehicle_type',
    //                     'conditions' => 'vehicle_model_condition',
    //                     'fuels' => 'vehicle_model_fuel',
    //                     'seller_types' => 'vehicle_model_seller_type',
    //                     'drive_wheels' => 'vehicle_model_drive_wheel',
    //                     'transmissions' => 'vehicle_model_transmission',
    //                     'detailed_titles' => 'vehicle_model_detailed_title',
    //                     'damages' => 'vehicle_model_damage',
    //                     'years' => 'vehicle_model_year',
    //                     'buyNows' => 'vehicle_model_buy_now',
    //                 ],
    //             ],
    //             'vehicle_types' => [
    //                 'model' => VehicleType::class,
    //                 'relations' => [
    //                     'manufacturers' => 'vehicle_type_manufacturer',
    //                     'vehicle_models' => 'vehicle_type_vehicle_model',
    //                     'conditions' => 'vehicle_type_condition',
    //                     'fuels' => 'vehicle_type_fuel',
    //                     'seller_types' => 'vehicle_type_seller_type',
    //                     'drive_wheels' => 'vehicle_type_drive_wheel',
    //                     'transmissions' => 'vehicle_type_transmission',
    //                     'detailed_titles' => 'vehicle_type_detailed_title',
    //                     'damages' => 'vehicle_type_damage',
    //                     'years' => 'vehicle_type_year',
    //                     'buyNows' => 'vehicle_type_buy_now',
    //                 ],
    //             ],
    //             'conditions' => [
    //                 'model' => Condition::class,
    //                 'relations' => [
    //                     'manufacturers' => 'condition_manufacturer',
    //                     'vehicle_models' => 'condition_vehicle_model',
    //                     'vehicle_types' => 'condition_vehicle_type',
    //                     'fuels' => 'condition_fuel',
    //                     'seller_types' => 'condition_seller_type',
    //                     'drive_wheels' => 'condition_drive_wheel',
    //                     'transmissions' => 'condition_transmission',
    //                     'detailed_titles' => 'condition_detailed_title',
    //                     'damages' => 'condition_damage',
    //                     'years' => 'condition_year',
    //                     'buyNows' => 'condition_buy_now',
    //                 ],
    //             ],
    //             'fuels' => [
    //                 'model' => Fuel::class,
    //                 'relations' => [
    //                     'manufacturers' => 'fuel_manufacturer',
    //                     'vehicle_models' => 'fuel_vehicle_model',
    //                     'vehicle_types' => 'fuel_vehicle_type',
    //                     'conditions' => 'fuel_condition',
    //                     'seller_types' => 'fuel_seller_type',
    //                     'drive_wheels' => 'fuel_drive_wheel',
    //                     'transmissions' => 'fuel_transmission',
    //                     'detailed_titles' => 'fuel_detailed_title',
    //                     'damages' => 'fuel_damage',
    //                     'years' => 'fuel_year',
    //                     'buyNows' => 'fuel_buy_now',
    //                 ],
    //             ],
    //             'seller_types' => [
    //                 'model' => SellerType::class,
    //                 'relations' => [
    //                     'manufacturers' => 'seller_type_manufacturer',
    //                     'vehicle_models' => 'seller_type_vehicle_model',
    //                     'vehicle_types' => 'seller_type_vehicle_type',
    //                     'conditions' => 'seller_type_condition',
    //                     'fuels' => 'seller_type_fuel',
    //                     'drive_wheels' => 'seller_type_drive_wheel',
    //                     'transmissions' => 'seller_type_transmission',
    //                     'detailed_titles' => 'seller_type_detailed_title',
    //                     'damages' => 'seller_type_damage',
    //                     'years' => 'seller_type_year',
    //                     'buyNows' => 'seller_type_buy_now',
    //                 ],
    //             ],
    //             'drive_wheels' => [
    //                 'model' => DriveWheel::class,
    //                 'relations' => [
    //                     'manufacturers' => 'drive_wheel_manufacturer',
    //                     'vehicle_models' => 'drive_wheel_vehicle_model',
    //                     'vehicle_types' => 'drive_wheel_vehicle_type',
    //                     'conditions' => 'drive_wheel_condition',
    //                     'fuels' => 'drive_wheel_fuel',
    //                     'seller_types' => 'drive_wheel_seller_type',
    //                     'transmissions' => 'drive_wheel_transmission',
    //                     'detailed_titles' => 'drive_wheel_detailed_title',
    //                     'damages' => 'drive_wheel_damage',
    //                     'years' => 'drive_wheel_year',
    //                     'buyNows' => 'drive_wheel_buy_now',
    //                 ],
    //             ],
    //             'transmissions' => [
    //                 'model' => Transmission::class,
    //                 'relations' => [
    //                     'manufacturers' => 'transmission_manufacturer',
    //                     'vehicle_models' => 'transmission_vehicle_model',
    //                     'vehicle_types' => 'transmission_vehicle_type',
    //                     'conditions' => 'transmission_condition',
    //                     'fuels' => 'transmission_fuel',
    //                     'seller_types' => 'transmission_seller_type',
    //                     'drive_wheels' => 'transmission_drive_wheel',
    //                     'detailed_titles' => 'transmission_detailed_title',
    //                     'damages' => 'transmission_damage',
    //                     'years' => 'transmission_year',
    //                     'buyNows' => 'transmission_buy_now',
    //                 ],
    //             ],
    //             'detailed_titles' => [
    //                 'model' => DetailedTitle::class,
    //                 'relations' => [
    //                     'manufacturers' => 'detailed_title_manufacturer',
    //                     'vehicle_models' => 'detailed_title_vehicle_model',
    //                     'vehicle_types' => 'detailed_title_vehicle_type',
    //                     'conditions' => 'detailed_title_condition',
    //                     'fuels' => 'detailed_title_fuel',
    //                     'seller_types' => 'detailed_title_seller_type',
    //                     'drive_wheels' => 'detailed_title_drive_wheel',
    //                     'transmissions' => 'detailed_title_transmission',
    //                     'damages' => 'detailed_title_damage',
    //                     'years' => 'detailed_title_year',
    //                     'buyNows' => 'detailed_title_buy_now',
    //                 ],
    //             ],
    //             'damages' => [
    //                 'model' => Damage::class,
    //                 'relations' => [
    //                     'manufacturers' => 'damage_manufacturer',
    //                     'vehicle_models' => 'damage_vehicle_model',
    //                     'vehicle_types' => 'damage_vehicle_type',
    //                     'conditions' => 'damage_condition',
    //                     'fuels' => 'damage_fuel',
    //                     'seller_types' => 'damage_seller_type',
    //                     'drive_wheels' => 'damage_drive_wheel',
    //                     'transmissions' => 'damage_transmission',
    //                     'detailed_titles' => 'damage_detailed_title',
    //                     'years' => 'damage_year',
    //                     'buyNows' => 'damage_buy_now',
    //                 ],
    //             ],
    //             'years' => [
    //                 'model' => Year::class,
    //                 'relations' => [
    //                     'manufacturers' => 'year_manufacturer',
    //                     'vehicle_models' => 'year_vehicle_model',
    //                     'vehicle_types' => 'year_vehicle_type',
    //                     'conditions' => 'year_condition',
    //                     'fuels' => 'year_fuel',
    //                     'seller_types' => 'year_seller_type',
    //                     'drive_wheels' => 'year_drive_wheel',
    //                     'transmissions' => 'year_transmission',
    //                     'detailed_titles' => 'year_detailed_title',
    //                     'damages' => 'year_damage',
    //                     'buyNows' => 'year_buy_now',
    //                 ],
    //             ],
    //             'buy_now' => [
    //                 'model' => BuyNow::class,
    //                 'relations' => [
    //                     'manufacturers' => 'buy_now_manufacturer',
    //                     'vehicle_models' => 'buy_now_vehicle_model',
    //                     'vehicle_types' => 'buy_now_vehicle_type',
    //                     'conditions' => 'buy_now_condition',
    //                     'fuels' => 'buy_now_fuel',
    //                     'seller_types' => 'buy_now_seller_type',
    //                     'drive_wheels' => 'buy_now_drive_wheel',
    //                     'transmissions' => 'buy_now_transmission',
    //                     'detailed_titles' => 'buy_now_detailed_title',
    //                     'damages' => 'buy_now_damage',
    //                     'years' => 'buy_now_year',
    //                 ],
    //             ],
    //         ];

    //         if (!isset($relationMappings[$attribute])) {
    //             return sendResponse(false, 400, 'Invalid attribute type.', '', 200);
    //         }

    //         $modelClass = $relationMappings[$attribute]['model'];
    //         $relations = $relationMappings[$attribute]['relations'];
    //         $attributes = array_fill_keys(array_keys($relations), []);
            
    //         // Handle the dynamic attribute explicitly for domain filtering and count
    //         if (in_array($attribute, ['manufacturers', 'vehicle_models', 'vehicle_types', 'conditions', 'fuels', 'seller_types', 'drive_wheels', 'transmissions', 'detailed_titles', 'damages'])) {
    //             // Define pivot table mappings for each attribute
    //             $pivotTableMappings = [
    //                 'manufacturers' => [
    //                     'pivotTable' => 'manufacturer_domain',
    //                     'foreignKey' => 'manufacturer_id',
    //                     'model' => Manufacturer::class,
    //                 ],
    //                 'vehicle_models' => [
    //                     'pivotTable' => 'vehicle_model_domain',
    //                     'foreignKey' => 'vehicle_model_id',
    //                     'model' => VehicleModel::class,
    //                 ],
    //                 'vehicle_types' => [
    //                     'pivotTable' => 'vehicle_type_domain',
    //                     'foreignKey' => 'vehicle_type_id',
    //                     'model' => VehicleType::class,
    //                 ],
    //                 'conditions' => [
    //                     'pivotTable' => 'condition_domain',
    //                     'foreignKey' => 'condition_id',
    //                     'model' => Condition::class,
    //                 ],
    //                 'fuels' => [
    //                     'pivotTable' => 'fuel_domain',
    //                     'foreignKey' => 'fuel_id',
    //                     'model' => Fuel::class,
    //                 ],
    //                 'seller_types' => [
    //                     'pivotTable' => 'seller_type_domain',
    //                     'foreignKey' => 'seller_type_id',
    //                     'model' => SellerType::class,
    //                 ],
    //                 'drive_wheels' => [
    //                     'pivotTable' => 'drive_wheel_domain',
    //                     'foreignKey' => 'drive_wheel_id',
    //                     'model' => DriveWheel::class,
    //                 ],
    //                 'transmissions' => [
    //                     'pivotTable' => 'transmission_domain',
    //                     'foreignKey' => 'transmission_id',
    //                     'model' => Transmission::class,
    //                 ],
    //                 'detailed_titles' => [
    //                     'pivotTable' => 'detailed_title_domain',
    //                     'foreignKey' => 'detailed_title_id',
    //                     'model' => DetailedTitle::class,
    //                 ],
    //                 'damages' => [
    //                     'pivotTable' => 'damage_domain',
    //                     'foreignKey' => 'damage_id',
    //                     'model' => Damage::class,
    //                 ],
    //             ];

    //             if (!isset($pivotTableMappings[$attribute])) {
    //                 return sendResponse(false, 400, 'Invalid attribute type.', '', 200);
    //             }

    //             $pivotTable = $pivotTableMappings[$attribute]['pivotTable'];
    //             $foreignKey = $pivotTableMappings[$attribute]['foreignKey'];
    //             $modelClass = $pivotTableMappings[$attribute]['model'];

    //             // Query the pivot table for the relevant data
    //             $data = \DB::table($pivotTable)
    //                 ->where('domain_id', $domainId)
    //                 ->select(
    //                     $foreignKey,
    //                     \DB::raw('CAST(SUM(count) AS UNSIGNED) as total_count')
    //                 )
    //                 ->groupBy($foreignKey)
    //                 ->get();

    //             $entityIds = $data->pluck($foreignKey)->toArray();

    //             // Fetch entities from the respective model
    //             $entities = $modelClass::whereIn('id', $entityIds)
    //                 ->get(['id', 'name']);

    //             // Map entities to include their count from the pivot table
    //             $attributes[$attribute] = $entities->map(function ($entity) use ($data, $foreignKey) {
    //                 $count = $data->firstWhere($foreignKey, $entity->id)->total_count ?? 0;
    //                 return [
    //                     'id' => $entity->id,
    //                     'name' => $entity->name,
    //                     'count' => $count, // Include count from pivot table
    //                 ];
    //             })->toArray();
    //         }

    //         // Define constraints for filtering by domain_id
    //         $relationConstraints = [];
    //         foreach ($relations as $relationName => $pivotTable) {
    //             $relationConstraints[$relationName] = function ($query) use ($domainId) {
    //                 $query->where('domain_id', $domainId);
    //             };
    //         }

    //         // Fetch data with domain_id filtering applied
    //         $modelClass::with($relationConstraints)
    //             ->whereIn('id', $ids)
    //             ->chunk(1000, function ($items) use (&$attributes, $relations) {
    //                 foreach ($items as $item) {
    //                     foreach ($relations as $relationName => $pivotTable) {
    //                         $relation = $item->$relationName;

    //                         if ($relation) {
    //                             foreach ($relation as $relatedItem) {
    //                                 $existingIndex = !empty($attributes[$relationName]) 
    //                                     ? array_search($relatedItem->id, array_column($attributes[$relationName], 'id')) 
    //                                     : false;

    //                                 if ($existingIndex !== false) {
    //                                     $attributes[$relationName][$existingIndex]['count'] += $relatedItem->pivot->count ?? 0;
    //                                 } else {
    //                                     $attributes[$relationName][] = [
    //                                         'id' => $relatedItem->id,
    //                                         'name' => $relatedItem->name,
    //                                         'count' => $relatedItem->pivot->count ?? 0,
    //                                     ];
    //                                 }
    //                             }
    //                         }
    //                     }
    //                 }
    //             });

    //         return sendResponse(true, 200, 'Attributes fetched successfully.', $attributes, 200);
    //     } catch (\Exception $ex) {
    //         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
    //     }
    // }

    public function testCron() 
        {
        $startTime = now();

        // Configuration for relationships
        $config = [
            'vehicle_types' => [
                'relations' => [
                    'manufacturers' => ['pivot' => 'vehicle_type_manufacturer', 'foreign_key' => 'manufacturer_id'],
                    'vehicle_models' => ['pivot' => 'vehicle_type_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
                    'conditions' => ['pivot' => 'vehicle_type_condition', 'foreign_key' => 'condition_id'],
                    'fuels' => ['pivot' => 'vehicle_type_fuel', 'foreign_key' => 'fuel_id'],
                    'seller_types' => ['pivot' => 'vehicle_type_seller_type', 'foreign_key' => 'seller_type_id'],
                    'drive_wheels' => ['pivot' => 'vehicle_type_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
                    'transmissions' => ['pivot' => 'vehicle_type_transmission', 'foreign_key' => 'transmission_id'],
                    'detailed_titles' => ['pivot' => 'vehicle_type_detailed_title', 'foreign_key' => 'detailed_title_id'],
                    'damages' => ['pivot' => 'vehicle_type_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
                    'domains' => ['pivot' => 'vehicle_type_domain', 'foreign_key' => 'domain_id'],
                    'years' => ['pivot' => 'vehicle_type_year', 'foreign_key' => 'year_id'],
                    'buy_nows' => ['pivot' => 'vehicle_type_buy_now', 'foreign_key' => 'buy_now_id'],
                ],
            ],
            'domains' => [
                'relations' => [
                    'manufacturers' => ['pivot' => 'domain_manufacturer', 'foreign_key' => 'manufacturer_id'],
                    'vehicle_models' => ['pivot' => 'domain_vehicle_model', 'foreign_key' => 'vehicle_model_id'],
                    'vehicle_types' => ['pivot' => 'domain_vehicle_type', 'foreign_key' => 'vehicle_type_id'],
                    'conditions' => ['pivot' => 'domain_condition', 'foreign_key' => 'condition_id'],
                    'fuels' => ['pivot' => 'domain_fuel', 'foreign_key' => 'fuel_id'],
                    'seller_types' => ['pivot' => 'domain_seller_type', 'foreign_key' => 'seller_type_id'],
                    'drive_wheels' => ['pivot' => 'domain_drive_wheel', 'foreign_key' => 'drive_wheel_id'],
                    'transmissions' => ['pivot' => 'domain_transmission', 'foreign_key' => 'transmission_id'],
                    'detailed_titles' => ['pivot' => 'domain_detailed_title', 'foreign_key' => 'detailed_title_id'],
                    'damages' => ['pivot' => 'domain_damage', 'foreign_key' => 'damage_id', 'custom_field' => 'damage_main'],
                    'years' => ['pivot' => 'domain_year', 'foreign_key' => 'year_id'],
                    'buy_nows' => ['pivot' => 'domain_buy_now', 'foreign_key' => 'buy_now_id'],
                ],
            ],
            
        ];

        foreach ($config as $base => $data) {
            $baseRecords = DB::table($base)->get();
            foreach ($baseRecords as $baseRecord) {
                $baseId = $baseRecord->id;
                $singularBase = Str::singular($base);

                $lastRunTime = DB::table('cron_run_history')
                    ->where('cron_name', 'process_vehicle_data')
                    ->whereNotNull('start_time')
                    ->latest('start_time')
                    ->value('start_time') ?? now()->subDay();
                    
                $relatedRecords = DB::table('vehicle_records')
                    // ->where("{$singularBase}_id", $baseId)
                    ->whereNotNull('sale_date')
                    ->where('processed_at', '>=', $lastRunTime)
                    ->where('is_new', true)
                    ->count();
                dd($relatedRecords) ;
                $allRelatedRecords[] = $relatedRecords;
                // dd($relatedRecords);
                
                foreach ($data['relations'] as $relation => $relationData) {
                    
                    $pivotTable = $relationData['pivot'];
                    $foreignKey = $relationData['foreign_key'];
                    
                    // Check if a custom field is specified
                    $fieldToGroupBy = $relationData['custom_field'] ?? $foreignKey;
       
                    $counts = $relatedRecords
                        ->filter(function ($record) use ($fieldToGroupBy) {
                          
                            return isset($record->{$fieldToGroupBy}) && is_numeric($record->{$fieldToGroupBy});
                        })
                        ->groupBy(function ($record) use ($fieldToGroupBy) {
                           
                            return $record->{$fieldToGroupBy} . '_' . $record->domain_id; // Group by field and domain_id
                        })
                        ->map(function ($group) {
                            return $group->count();
                        });
                    

                    foreach ($counts as $groupKey => $count) {

                        [$relatedId, $domainId] = explode('_', $groupKey);
                        
                        if ($relatedId === null || !is_numeric($relatedId) || $domainId === null || !is_numeric($domainId)) {
                            continue;
                        }

                        $existingRecord = DB::table($pivotTable)
                            ->where("{$singularBase}_id", $baseId)
                            ->where($foreignKey, $relatedId)
                            ->where('domain_id', $domainId)
                            ->first();
                        if ($existingRecord) {
                            // Increment the count if the record already exists
                            $updatedCount = $existingRecord->count + $count;
                            DB::table($pivotTable)->where('id', $existingRecord->id)->update([
                                'count' => $updatedCount,
                                'updated_at' => now(),
                            ]);
                        } else {
                            // Insert a new record if it doesn't exist
                            DB::table($pivotTable)->insert([
                                "{$singularBase}_id" => $baseId,
                                $foreignKey => $relatedId,
                                'domain_id' => $domainId,
                                'count' => $count,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                   
                }
            }
        }
 
        DB::table('vehicle_records')
    ->whereNotNull('sale_date')
    ->where('processed_at', '>=', $lastRunTime)
    ->where('is_new', false)
    ->update(['is_new' => false, 'processed_at' => now()]);

        $latestRecords = DB::table('vehicle_records')
                    ->whereNotNull('sale_date')
                    ->where('processed_at', '>=', $lastRunTime)
                    ->where('is_new', false)
                    ->get();
         // Mark processed records
        DB::table('vehicle_records')
            ->whereIn('id', $latestRecords->pluck('id'))
            ->update(['is_new' => false, 'processed_at' => now()]);

            return'sdd';
    }
    // public function filterAttributes(Request $request)
    // {
    //     try {
    //         // Initialize the base query
    //         $query = VehicleRecord::query();

    //         $query->whereNotNull('sale_date')->where('is_new', false);

    //         // Handle domain filter
    //         if ($request->has('domain_id')) {
    //             $query->where('domain_id', $request->input('domain_id'));
    //         }

    //         // Define filters dynamically
    //         $filters = [
    //             'manufacturers' => 'manufacturer_id',
    //             'vehicle_models' => 'vehicle_model_id',
    //             'vehicle_types' => 'vehicle_type_id',
    //             'conditions' => 'condition_id',
    //             'fuels' => 'fuel_id',
    //             'seller_types' => 'seller_type_id',
    //             'drive_wheels' => 'drive_wheel_id',
    //             'transmissions' => 'transmission_id',
    //             'detailed_titles' => 'detailed_title_id',
    //             'damages' => 'damage_id',
    //             'years' => 'year',
    //             'buy_now' => 'buy_now_id',
    //         ];

    //         // Apply filters dynamically
    //         foreach ($filters as $requestKey => $dbColumn) {
    //             if ($request->has($requestKey) && is_array($request->input($requestKey))) {
    //                 $query->whereIn($dbColumn, $request->input($requestKey));
    //             }
    //         }

    //          // Prepare the response structure
    //         $response = [
    //             "data" => [
    //                 "manufacturers" => [],
    //                 "vehicle_models" => [],
    //                 "vehicle_types" => [],
    //                 "conditions" => [],
    //                 "fuels" => [],
    //                 "seller_types" => [],
    //                 "drive_wheels" => [],
    //                 "transmissions" => [],
    //                 "detailed_titles" => [],
    //                 "damages" => [],
    //                 "years" => []
    //             ]
    //         ];

    //         // Fetch counts for each attribute
    //         foreach ($filters as $key => $column) {
    //             $attributeQuery = clone $query;
    //             $results = $attributeQuery
    //                 ->selectRaw("$column, COUNT(*) as count")
    //                 ->groupBy($column)
    //                 ->with($key) // Fetch related data if applicable
    //                 ->get();

    //             // Map results to the response structure
    //             $response['data'][$key] = $results->map(function ($item) use ($key) {
    //                 return [
    //                     "{$key}_id" => $item->{$key . '_id'},
    //                     'name' => $item->{$key}->name ?? 'Unknown',
    //                     'count' => $item->count
    //                 ];
    //             });
    //         }

    //         return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
    //     } catch (\Exception $ex) {
    //         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
    //     }
    // }


    // public function filterAttributes(Request $request)
    // {
    //     try {
    //         // Initialize the base query
    //         $query = VehicleRecord::query();

    //         $query->whereNotNull('sale_date')->where('is_new', false);

    //         // Apply domain filter if provided
    //         if ($request->has('domain_id')) {
    //             $query->where('domain_id', $request->input('domain_id'));
    //         }

    //         // Define filters and relationships dynamically
    //         $filters = [
    //             'manufacturers' => ['column' => 'manufacturer_id', 'relation' => 'manufacturer'],
    //             'vehicle_models' => ['column' => 'vehicle_model_id', 'relation' => 'vehicleModel'],
    //             'vehicle_types' => ['column' => 'vehicle_type_id', 'relation' => 'vehicleType'],
    //             'conditions' => ['column' => 'condition_id', 'relation' => 'condition'],
    //             'fuels' => ['column' => 'fuel_id', 'relation' => 'fuel'],
    //             'seller_types' => ['column' => 'seller_type_id', 'relation' => 'sellerType'],
    //             'drive_wheels' => ['column' => 'drive_wheel_id', 'relation' => 'driveWheel'],
    //             'transmissions' => ['column' => 'transmission_id', 'relation' => 'transmission'],
    //             'detailed_titles' => ['column' => 'detailed_title_id', 'relation' => 'detailedTitle'],
    //             'damages' => ['column' => 'damage_id', 'relation' => 'damageMain'], // Assuming main damage
            
    //         ];

    //         // Apply input filters dynamically
    //         foreach ($filters as $key => $details) {
    //             if ($request->has($key) && is_array($request->input($key))) {
    //                 $query->whereIn($details['column'], $request->input($key));
    //             }
    //         }
           
          
    //         // Prepare the response structure
    //         $response = ["data" => array_fill_keys(array_keys($filters), [])];

    //         // Fetch counts for each attribute
    //         foreach ($filters as $key => $details) {
                
    //             $attributeQuery = clone $query;
    //             $results = $attributeQuery
    //                 ->selectRaw("{$details['column']} as id, COUNT(*) as count")
    //                 ->groupBy($details['column'])
    //                 ->with($details['relation']) // Include related model if relation exists
    //                 ->get();
              
    //             // Map results to the response structure
    //             $response['data'][$key] = $results->map(function ($item) use ($details) {
                    
                
    //                 $relatedName = $details['relation'] 
    //                     ? $item->{$details['relation']}->name ?? 'Unknown' 
    //                     : $item->id; // Fallback for non-related attributes like `year`
                     
    //                 return [
    //                     "{$details['relation']}_id" => $item->id,
    //                     'name' => $relatedName,
    //                     'count' => $item->count
    //                 ];
    //             });
    //         }

    //         return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
    //     } catch (\Exception $ex) {
    //         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
    //     }
    // }

    // Final static query
    // public function filterAttributes(Request $request)
    // {
    //     try {
    //         // Initialize the base query
    //         $query = VehicleRecord::query();

    //         $query->whereNotNull('sale_date')->where('is_new', false);

    //         // Apply domain filter if provided
    //         if ($request->has('domain_id')) {
    //             $query->where('domain_id', $request->input('domain_id'));
    //         }

    //         // Define filters and relationships dynamically
    //         $filters = [
    //             'manufacturers' => ['column' => 'manufacturer_id', 'relation' => 'manufacturer', 'table' => 'manufacturers'],
    //             'vehicle_models' => ['column' => 'vehicle_model_id', 'relation' => 'vehicleModel', 'table' => 'vehicle_models'],
    //             'vehicle_types' => ['column' => 'vehicle_type_id', 'relation' => 'vehicleType', 'table' => 'vehicle_types'],
    //             'conditions' => ['column' => 'condition_id', 'relation' => 'condition', 'table' => 'conditions'],
    //             'fuels' => ['column' => 'fuel_id', 'relation' => 'fuel', 'table' => 'fuels'],
    //             'seller_types' => ['column' => 'seller_type_id', 'relation' => 'sellerType', 'table' => 'seller_types'],
    //             'drive_wheels' => ['column' => 'drive_wheel_id', 'relation' => 'driveWheel', 'table' => 'drive_wheels'],
    //             'transmissions' => ['column' => 'transmission_id', 'relation' => 'transmission', 'table' => 'transmissions'],
    //             'detailed_titles' => ['column' => 'detailed_title_id', 'relation' => 'detailedTitle', 'table' => 'detailed_titles'],
    //             'damages' => ['column' => 'damage_id', 'relation' => 'damageMain', 'table' => 'damages'], // Assuming main damage
    //         ];

    //         // Apply input filters dynamically
    //         foreach ($filters as $key => $details) {
    //             if ($request->has($key) && is_array($request->input($key))) {
    //                 $query->whereIn($details['column'], $request->input($key));
    //             }
    //         }

    //         // Fetch counts for each attribute
    //         foreach ($filters as $key => $details) {
    //             $attributeQuery = clone $query;

    //             // Join the related table using the specified table name and column
    //             $results = $attributeQuery
    //                 ->selectRaw("{$details['column']} as id, COUNT(*) as count, r.name as related_name")
    //                 ->join("{$details['table']} as r", "r.id", "=", "{$details['column']}") // Join related table
    //                 ->groupBy("{$details['column']}", 'r.name') // Include the related field in GROUP BY
    //                 ->get();

    //             // Map results to the response structure
    //             $response[$key] = $results->map(function ($item) use ($details) {
    //                 $relatedName = $item->related_name ?? 'Unknown';  // Access the related field directly
                    
    //                 return [
    //                     "{$details['relation']}_id" => $item->id,
    //                     'name' => $relatedName,
    //                     'count' => $item->count
    //                 ];
    //             });
    //         }

    //         return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
    //     } catch (\Exception $ex) {
    //         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
    //     }
    // }

    // Final dynamic query with proper counts
// public function filterAttributes(Request $request)
// {
//     try {
//         // Define filters and relationships dynamically
//         $filters = [
//             'manufacturers' => ['column' => 'manufacturer_id', 'relation' => 'manufacturer', 'table' => 'manufacturers'],
//             'vehicle_models' => ['column' => 'vehicle_model_id', 'relation' => 'vehicleModel', 'table' => 'vehicle_models'],
//             'vehicle_types' => ['column' => 'vehicle_type_id', 'relation' => 'vehicleType', 'table' => 'vehicle_types'],
//             'conditions' => ['column' => 'condition_id', 'relation' => 'condition', 'table' => 'conditions'],
//             'fuels' => ['column' => 'fuel_id', 'relation' => 'fuel', 'table' => 'fuels'],
//             'seller_types' => ['column' => 'seller_type_id', 'relation' => 'sellerType', 'table' => 'seller_types'],
//             'drive_wheels' => ['column' => 'drive_wheel_id', 'relation' => 'driveWheel', 'table' => 'drive_wheels'],
//             'transmissions' => ['column' => 'transmission_id', 'relation' => 'transmission', 'table' => 'transmissions'],
//             'detailed_titles' => ['column' => 'detailed_title_id', 'relation' => 'detailedTitle', 'table' => 'detailed_titles'],
//             'damages' => ['column' => 'damage_id', 'relation' => 'damageMain', 'table' => 'damages'],
//         ];

//             $response = [];

//             foreach ($filters as $key => $details) {
//                 $query = VehicleRecord::query()
//                     ->whereNotNull('sale_date');
//                     // ->where('is_new', false);

//                 // Apply domain filter if provided
//                 if ($request->has('domain_id')) {
//                     $query->where('domain_id', $request->input('domain_id'));
//                 }
//                 // Handle the 'buy_now'
//                 if ($request->has('buy_now')) {
//                     if ($request->buy_now == true) {  
//                         $buy_now_id = BuyNow::where('name', 'buyNowWithPrice')->pluck('id');
//                         $query->where('buy_now_id', $buy_now_id);
//                     } elseif ($request->buy_now == false) {
//                         $buy_now_ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
//                                 ->pluck('id')
//                                 ->toArray();
//                             $query->whereIn('buy_now_id', $buy_now_ids);
//                     }
//                 }

//                 // Handling 'year_from' and 'year_to'
//                 if ($request->has('year_from') && $request->has('year_to')) {
//                     $query->whereBetween('year', [(int) $request->input('year_from'), (int) $request->input('year_to')]);
//                 }

//                 // Handling 'odometer_min' and 'odometer_max'
//                 if ($request->has('odometer_min') && $request->has('odometer_max')) {
//                     // Remove commas and cast to integers
//                     $odometerMin = (int) str_replace(',', '', $request->input('odometer_min'));
//                     $odometerMax = (int) str_replace(',', '', $request->input('odometer_max'));
                    
//                     // Perform query filtering
//                     $query->whereBetween('odometer_mi', [$odometerMin, $odometerMax]);
//                 }

//                 // Handling 'auction_date' and 'auction_date_from' and 'auction_date_to
//                 if ($request->has('auction_date')) {
//                     $auctionDateInput = $request->input('auction_date');

//                     // Check if it's a date range (contains "to")
//                     if (strpos($auctionDateInput, 'to') !== false) {
//                         [$auctionDateFrom, $auctionDateTo] = explode(' to ', $auctionDateInput);
//                         $auctionDateFrom = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom))->startOfDay();
//                         $auctionDateTo = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateTo))->endOfDay();

//                         $query->whereBetween('sale_date', [$auctionDateFrom, $auctionDateTo]);
//                     } else {
//                         // Single date case
//                         $auctionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $auctionDateInput)->startOfDay();
//                         $query->whereDate('sale_date', $auctionDate);
//                     }
//                 }

//                 // Apply input filters dynamically
//                 foreach ($filters as $filterKey => $filterDetails) {
//                     if ($request->has($filterKey) && is_array($request->input($filterKey))) {
//                         $query->whereIn($filterDetails['column'], $request->input($filterKey));
//                     }
//                 }

//                 // Fetch aggregated data with counts
//                 $results = $query
//                     ->selectRaw("{$details['column']} as id, COUNT(*) as count")
//                     ->groupBy("{$details['column']}")
//                     ->get();

//                 // Fetch related names in bulk for better performance
//                 $relatedNames = DB::table($details['table'])
//                     ->whereIn('id', $results->pluck('id'))
//                     ->pluck('name', 'id');

//                 // Map results to the response structure
//                 $response[$key] = $results->map(function ($item) use ($relatedNames, $details) {
//                     return [
//                         "id" => $item->id,
//                         'name' => $relatedNames[$item->id] ?? 'Unknown',
//                         'count' => $item->count,
//                     ];
//                 });
//             }

//         return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
//     } catch (\Exception $ex) {
//         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
//     }
// }

// Final dynamic query with proper counts with pagination
// public function filterAttributes(Request $request)
// {
//     try {
//         // Define filters and relationships dynamically
//         $filters = [
//             'manufacturers' => ['column' => 'manufacturer_id', 'relation' => 'manufacturer', 'table' => 'manufacturers', 'paginate' => true],
//             'vehicle_models' => ['column' => 'vehicle_model_id', 'relation' => 'vehicleModel', 'table' => 'vehicle_models', 'paginate' => true],
//             'vehicle_types' => ['column' => 'vehicle_type_id', 'relation' => 'vehicleType', 'table' => 'vehicle_types', 'paginate' => false],
//             'conditions' => ['column' => 'condition_id', 'relation' => 'condition', 'table' => 'conditions', 'paginate' => false],
//             'fuels' => ['column' => 'fuel_id', 'relation' => 'fuel', 'table' => 'fuels', 'paginate' => false],
//             'seller_types' => ['column' => 'seller_type_id', 'relation' => 'sellerType', 'table' => 'seller_types', 'paginate' => false],
//             'drive_wheels' => ['column' => 'drive_wheel_id', 'relation' => 'driveWheel', 'table' => 'drive_wheels', 'paginate' => false],
//             'transmissions' => ['column' => 'transmission_id', 'relation' => 'transmission', 'table' => 'transmissions', 'paginate' => false],
//             'detailed_titles' => ['column' => 'detailed_title_id', 'relation' => 'detailedTitle', 'table' => 'detailed_titles', 'paginate' => true],
//             'damages' => ['column' => 'damage_id', 'relation' => 'damageMain', 'table' => 'damages', 'paginate' => false],
//         ];

//         $response = [];
//         $page = $request->input('page', 1); // Default to page 1 if not provided
//         $perPage = $request->input('size', 10); // Default to 10 items per page

//         foreach ($filters as $key => $details) {
//             $query = VehicleRecord::query()
//                 ->whereNotNull('sale_date');
//                 // ->where('is_new', false);

//             // Apply domain filter if provided
//             if ($request->has('domain_id')) {
//                 $query->where('domain_id', $request->input('domain_id'));
//             }

//             // Handle the 'buy_now'
//             if ($request->has('buy_now')) {
//                 if ($request->buy_now == true) {
//                     $buy_now_id = BuyNow::where('name', 'buyNowWithPrice')->pluck('id');
//                     $query->where('buy_now_id', $buy_now_id);
//                 } elseif ($request->buy_now == false) {
//                     $buy_now_ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
//                         ->pluck('id')
//                         ->toArray();
//                     $query->whereIn('buy_now_id', $buy_now_ids);
//                 }
//             }

//             // Handling 'year_from' and 'year_to'
//             if ($request->has('year_from') && $request->has('year_to')) {
//                 $query->whereBetween('year', [(int) $request->input('year_from'), (int) $request->input('year_to')]);
//             }

//             // Handling 'odometer_min' and 'odometer_max'
//             if ($request->has('odometer_min') && $request->has('odometer_max')) {
//                 // Remove commas and cast to integers
//                 $odometerMin = (int) str_replace(',', '', $request->input('odometer_min'));
//                 $odometerMax = (int) str_replace(',', '', $request->input('odometer_max'));
                
//                 // Perform query filtering
//                 $query->whereBetween('odometer_mi', [$odometerMin, $odometerMax]);
//             }

//             // Handling 'auction_date' and 'auction_date_from' and 'auction_date_to'
//             if ($request->has('auction_date')) {
//                 $auctionDateInput = $request->input('auction_date');

//                 // Check if it's a date range (contains "to")
//                 if (strpos($auctionDateInput, 'to') !== false) {
//                     [$auctionDateFrom, $auctionDateTo] = explode(' to ', $auctionDateInput);
//                     $auctionDateFrom = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom))->startOfDay();
//                     $auctionDateTo = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateTo))->endOfDay();

//                     $query->whereBetween('sale_date', [$auctionDateFrom, $auctionDateTo]);
//                 } else {
//                     // Single date case
//                     $auctionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $auctionDateInput)->startOfDay();
//                     $query->whereDate('sale_date', $auctionDate);
//                 }
//             }

//             // Apply input filters dynamically
//             foreach ($filters as $filterKey => $filterDetails) {
//                 if ($request->has($filterKey) && is_array($request->input($filterKey))) {
//                     $query->whereIn($filterDetails['column'], $request->input($filterKey));
//                 }
//             }

//             // Apply pagination only for manufacturers, vehicle_models, and detailed_titles
//             if ($details['paginate']) {
//                 $results = $query
//                     ->selectRaw("{$details['column']} as id, COUNT(*) as count")
//                     ->groupBy("{$details['column']}")
//                     ->paginate($perPage, ['*'], 'page', $page);
//             } else {
//                 // Fetch all data for other attributes
//                 $results = $query
//                     ->selectRaw("{$details['column']} as id, COUNT(*) as count")
//                     ->groupBy("{$details['column']}")
//                     ->get();
//             }

//             // Fetch related names in bulk for better performance
//             $relatedNames = DB::table($details['table'])
//                 ->whereIn('id', $results->pluck('id'))
//                 ->pluck('name', 'id');

//             // Map results to the response structure
//             $response[$key] = $results->map(function ($item) use ($relatedNames, $details) {
//                 return [
//                     "id" => $item->id,
//                     'name' => $relatedNames[$item->id] ?? 'Unknown',
//                     'count' => $item->count,
//                 ];
//             });
//         }

//         return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
//     } catch (\Exception $ex) {
//         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
//     }
// }


// Final dynamic query with proper counts with pagination and specific
// public function filterAttributes(Request $request)
// {
//     try {
//         // Define filters and relationships dynamically
//         $filters = [
//             'manufacturers' => ['column' => 'manufacturer_id', 'relation' => 'manufacturer', 'table' => 'manufacturers', 'paginate' => true],
//             'vehicle_models' => ['column' => 'vehicle_model_id', 'relation' => 'vehicleModel', 'table' => 'vehicle_models', 'paginate' => true],
//             'vehicle_types' => ['column' => 'vehicle_type_id', 'relation' => 'vehicleType', 'table' => 'vehicle_types', 'paginate' => true],
//             'conditions' => ['column' => 'condition_id', 'relation' => 'condition', 'table' => 'conditions', 'paginate' => true],
//             'fuels' => ['column' => 'fuel_id', 'relation' => 'fuel', 'table' => 'fuels', 'paginate' => true],
//             'seller_types' => ['column' => 'seller_type_id', 'relation' => 'sellerType', 'table' => 'seller_types', 'paginate' => true],
//             'drive_wheels' => ['column' => 'drive_wheel_id', 'relation' => 'driveWheel', 'table' => 'drive_wheels', 'paginate' => true],
//             'transmissions' => ['column' => 'transmission_id', 'relation' => 'transmission', 'table' => 'transmissions', 'paginate' => true],
//             'detailed_titles' => ['column' => 'detailed_title_id', 'relation' => 'detailedTitle', 'table' => 'detailed_titles', 'paginate' => true],
//             'damages' => ['column' => 'damage_id', 'relation' => 'damageMain', 'table' => 'damages', 'paginate' => true],
//         ];

//         $response = [];
//         $page = $request->input('page', 1); // Default to page 1 if not provided
//         $perPage = $request->input('size', 20); // Default to 20 items per page

//         // Check if specific filters are set
//         $listing = $request->input('listing');

//         // Map of listing to active filter key
//         $validListings = [
//             'manufacturers', 'vehicle_models', 'detailed_titles', 'vehicle_types',
//             'conditions', 'fuels', 'seller_types', 'drive_wheels', 'transmissions', 'damages'
//         ];

//         // Check if the listing is valid and determine the active filter key
//         $activeFilterKey = in_array($listing, $validListings) ? $listing : null;

//         foreach ($filters as $key => $details) {
//             // Skip other attributes if an active filter is set
//             if ($activeFilterKey && $key !== $activeFilterKey) {
//                 continue;
//             }

//             $query = VehicleRecord::query()->whereNotNull('sale_date');

//             // Apply domain filter if provided
//             if ($request->has('domain_id')) {
//                 $query->where('domain_id', $request->input('domain_id'));
//             }

//             // Handle the 'buy_now'
//             if ($request->has('buy_now')) {
//                 if ($request->buy_now == true) {
//                     $buy_now_id = BuyNow::where('name', 'buyNowWithPrice')->pluck('id');
//                     $query->where('buy_now_id', $buy_now_id);
//                 } elseif ($request->buy_now == false) {
//                     $buy_now_ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
//                         ->pluck('id')
//                         ->toArray();
//                     $query->whereIn('buy_now_id', $buy_now_ids);
//                 }
//             }

//             // Handling 'year_from' and 'year_to'
//             if ($request->has('year_from') && $request->has('year_to')) {
//                 $query->whereBetween('year', [(int) $request->input('year_from'), (int) $request->input('year_to')]);
//             }

//             // Handling 'odometer_min' and 'odometer_max'
//             if ($request->has('odometer_min') && $request->has('odometer_max')) {
//                 $query->whereBetween('odometer_mi', [
//                     (int) str_replace(',', '', $request->input('odometer_min')),
//                     (int) str_replace(',', '', $request->input('odometer_max'))
//                 ]);
//             }

//             // Handling 'auction_date' and 'auction_date_from' and 'auction_date_to'
//             if ($request->has('auction_date')) {
//                 $auctionDateInput = $request->input('auction_date');

//                 // Check if it's a date range (contains "to")
//                 if (strpos($auctionDateInput, 'to') !== false) {
//                     [$auctionDateFrom, $auctionDateTo] = explode(' to ', $auctionDateInput);
//                     $auctionDateFrom = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom))->startOfDay();
//                     $auctionDateTo = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateTo))->endOfDay();

//                     $query->whereBetween('sale_date', [$auctionDateFrom, $auctionDateTo]);
//                 } else {
//                     // Single date case
//                     $auctionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $auctionDateInput)->startOfDay();
//                     $query->whereDate('sale_date', $auctionDate);
//                 }
//             }

//             // Apply input filters dynamically
//             foreach ($filters as $filterKey => $filterDetails) {
//                 if ($request->has($filterKey) && is_array($request->input($filterKey))) {
//                     $query->whereIn($filterDetails['column'], $request->input($filterKey));
//                 }
//             }

//             // Apply pagination logic dynamically
//             if ($details['paginate'] && (!$activeFilterKey || $key === $activeFilterKey)) {
//                 $results = $query
//                     ->selectRaw("{$details['column']} as id, COUNT(*) as count")
//                     ->groupBy("{$details['column']}")
//                     ->paginate($perPage, ['*'], 'page', $page);
//             } 
//             else {
//                 $results = $query
//                     ->selectRaw("{$details['column']} as id, COUNT(*) as count")
//                     ->groupBy("{$details['column']}")
//                     ->get();
//             }

//             // Fetch related names in bulk
//             $relatedNames = DB::table($details['table'])
//                 ->whereIn('id', $results->pluck('id'))
//                 ->pluck('name', 'id');

//             // Map results to the response structure
//             $response[$key] = $results->map(function ($item) use ($relatedNames, $details) {
//                 return [
//                     "id" => $item->id,
//                     'name' => $relatedNames[$item->id] ?? 'Unknown',
//                     'count' => $item->count,
//                 ];
//             });
//         }
        
//         // Return only the active filter's data if a specific filter is set
//         if ($activeFilterKey) {
//             return sendResponse(true, 200, ucfirst(str_replace('_', ' ', $activeFilterKey)) . ' Fetched Successfully!', [
//                 $activeFilterKey => $response[$activeFilterKey]
//             ], 200);
//         }

//         return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
//     } catch (\Exception $ex) {
//         return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
//     }
// }


public function filterAttributes(Request $request)
{
    try {
        // Define filters and relationships dynamically
        $filters = [
            'manufacturers' => ['column' => 'manufacturer_id', 'relation' => 'manufacturer', 'table' => 'manufacturers', 'paginate' => true],
            'vehicle_models' => ['column' => 'vehicle_model_id', 'relation' => 'vehicleModel', 'table' => 'vehicle_models', 'paginate' => true],
            'vehicle_types' => ['column' => 'vehicle_type_id', 'relation' => 'vehicleType', 'table' => 'vehicle_types', 'paginate' => true],
            'conditions' => ['column' => 'condition_id', 'relation' => 'condition', 'table' => 'conditions', 'paginate' => true],
            'fuels' => ['column' => 'fuel_id', 'relation' => 'fuel', 'table' => 'fuels', 'paginate' => true],
            'seller_types' => ['column' => 'seller_type_id', 'relation' => 'sellerType', 'table' => 'seller_types', 'paginate' => true],
            'drive_wheels' => ['column' => 'drive_wheel_id', 'relation' => 'driveWheel', 'table' => 'drive_wheels', 'paginate' => true],
            'transmissions' => ['column' => 'transmission_id', 'relation' => 'transmission', 'table' => 'transmissions', 'paginate' => true],
            'detailed_titles' => ['column' => 'detailed_title_id', 'relation' => 'detailedTitle', 'table' => 'detailed_titles', 'paginate' => true],
            'damages' => ['column' => 'damage_id', 'relation' => 'damageMain', 'table' => 'damages', 'paginate' => true],
        ];

        $response = [];
        $page = $request->input('page', 1); // Default to page 1 if not provided
        $perPage = $request->input('size', 20); // Default to 20 items per page

        // Get the current_hit_attribute from the request
        $currentHitAttribute = $request->input('current_hit_attribute');

        // Check if specific filters are set
        $listing = $request->input('listing');

        // Map of listing to active filter key
        $validListings = [
            'manufacturers', 'vehicle_models', 'detailed_titles', 'vehicle_types',
            'conditions', 'fuels', 'seller_types', 'drive_wheels', 'transmissions', 'damages'
        ];

        // Check if the listing is valid and determine the active filter key
        $activeFilterKey = in_array($listing, $validListings) ? $listing : null;

        foreach ($filters as $key => $details) {
            // Skip other attributes if an active filter is set
            if ($activeFilterKey && $key !== $activeFilterKey) {
                continue;
            }

            // If current_hit_attribute is set and matches the current filter, skip changing this filter
            // if ($currentHitAttribute && $currentHitAttribute === $key) {

            //     // Get the current results for this filter (do not change them)
            //     $existingResults = DB::table('vehicle_records')
            //         ->whereNotNull('sale_date');

            //     // Apply domain filter if provided
            //     if ($request->has('domain_id')) {
            //         $existingResults->where('domain_id', $request->input('domain_id'));
            //     }

            //     // Handle the 'buy_now' logic
            //     if ($request->has('buy_now')) {
            //         if ($request->buy_now == true) {
            //             $buy_now_id = BuyNow::where('name', 'buyNowWithPrice')->pluck('id')->first();
            //             $existingResults->where('buy_now_id', $buy_now_id);
            //         } elseif ($request->buy_now == false) {
            //             $buy_now_ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
            //                 ->pluck('id')
            //                 ->toArray();
            //             $existingResults->whereIn('buy_now_id', $buy_now_ids);
            //         }
            //     }

            //     // Handling 'year_from' and 'year_to'
            //     if ($request->has('year_from') && $request->has('year_to')) {
            //         $existingResults->whereBetween('year', [(int) $request->input('year_from'), (int) $request->input('year_to')]);
            //     }

            //     // Handling 'odometer_min' and 'odometer_max'
            //     if ($request->has('odometer_min') && $request->has('odometer_max')) {
            //         $existingResults->whereBetween('odometer_mi', [
            //             (int) str_replace(',', '', $request->input('odometer_min')),
            //             (int) str_replace(',', '', $request->input('odometer_max'))
            //         ]);
            //     }

            //     // Handling 'auction_date' and 'auction_date_from' and 'auction_date_to'
            //     if ($request->has('auction_date')) {
            //         $auctionDateInput = $request->input('auction_date');

            //         // Check if it's a date range (contains "to")
            //         if (strpos($auctionDateInput, 'to') !== false) {
            //             [$auctionDateFrom, $auctionDateTo] = explode(' to ', $auctionDateInput);
            //             $auctionDateFrom = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom))->startOfDay();
            //             $auctionDateTo = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateTo))->endOfDay();

            //             $existingResults->whereBetween('sale_date', [$auctionDateFrom, $auctionDateTo]);
            //         } else {
            //             // Single date case
            //             $auctionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $auctionDateInput)->startOfDay();
            //             $existingResults->whereDate('sale_date', $auctionDate);
            //         }
            //     }

            //     // Fetch results
            //     $existingResults = $existingResults->select("{$details['column']} as id", DB::raw("COUNT(*) as count"))
            //         ->groupBy("{$details['column']}")
            //         ->get(); // Fetch the data here

            //     // Fetch related names in bulk
            //     $relatedNames = DB::table($details['table'])
            //         ->whereIn('id', $existingResults->pluck('id'))
            //         ->pluck('name', 'id');

            //     // Map results to the response structure
            //     $response[$key] = $existingResults->map(function ($item) use ($relatedNames, $details) {
            //         return [
            //             "id" => $item->id,
            //             'name' => $relatedNames[$item->id] ?? 'Unknown',
            //             'count' => $item->count,
            //         ];
            //     });

            //     continue; // Skip to the next filter
            // }

            $query = VehicleRecord::query()->whereNotNull('sale_date');

            // Apply domain filter if provided
            if ($request->has('domain_id')) {
                $query->where('domain_id', $request->input('domain_id'));
            }

            // Handle the 'buy_now' logic
            if ($request->has('buy_now')) {
                if ($request->buy_now == true) {
                    $buy_now_id = BuyNow::where('name', 'buyNowWithPrice')->pluck('id');
                    $query->where('buy_now_id', $buy_now_id);
                } elseif ($request->buy_now == false) {
                    $buy_now_ids = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])
                        ->pluck('id')
                        ->toArray();
                    $query->whereIn('buy_now_id', $buy_now_ids);
                }
            }

            // Handling 'year_from' and 'year_to'
            if ($request->has('year_from') && $request->has('year_to')) {
                $query->whereBetween('year', [(int) $request->input('year_from'), (int) $request->input('year_to')]);
            }

            // Handling 'odometer_min' and 'odometer_max'
            if ($request->has('odometer_min') && $request->has('odometer_max')) {
                $query->whereBetween('odometer_mi', [
                    (int) str_replace(',', '', $request->input('odometer_min')),
                    (int) str_replace(',', '', $request->input('odometer_max'))
                ]);
            }

            // Handling 'auction_date' and 'auction_date_from' and 'auction_date_to'
            if ($request->has('auction_date')) {
                $auctionDateInput = $request->input('auction_date');

                // Check if it's a date range (contains "to")
                if (strpos($auctionDateInput, 'to') !== false) {
                    [$auctionDateFrom, $auctionDateTo] = explode(' to ', $auctionDateInput);
                    $auctionDateFrom = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom))->startOfDay();
                    $auctionDateTo = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateTo))->endOfDay();

                    $query->whereBetween('sale_date', [$auctionDateFrom, $auctionDateTo]);
                } else {
                    // Single date case
                    $auctionDate = \Carbon\Carbon::createFromFormat('Y-m-d', $auctionDateInput)->startOfDay();
                    $query->whereDate('sale_date', $auctionDate);
                }
            }

            // If current_hit_attribute is set and matches the current filter, skip changing this filter
            if ($currentHitAttribute && $currentHitAttribute === $key) {

                $existingResults = clone $query;

                foreach ($filters as $filterKey => $filterDetails) {
                    // Check if the current filter key matches the current_hit_attribute
                    if ($filterKey === $currentHitAttribute) {
                        // Skip applying whereIn if it matches the current_hit_attribute
                        continue;
                    }
    
                    if ($request->has($filterKey) && is_array($request->input($filterKey))) {
                        // Use the correct column from the filters array for dynamic filtering
                        $existingResults->whereIn($filterDetails['column'], $request->input($filterKey));
                    }
                }

                // Fetch results
                $existingResults = $existingResults->select("{$details['column']} as id", DB::raw("COUNT(*) as count"))
                    ->groupBy("{$details['column']}")
                    ->paginate($perPage, ['*'], 'page', $page); // Fetch the data here

                // Fetch related names in bulk
                $relatedNames = DB::table($details['table'])
                    ->whereIn('id', $existingResults->pluck('id'))
                    ->pluck('name', 'id');


                // Map results to the response structure
                $response[$key] = $existingResults->map(function ($item) use ($relatedNames, $details) {
                    return [
                        "id" => $item->id,
                        'name' => $relatedNames[$item->id] ?? 'Unknown',
                        'count' => $item->count,
                    ];
                });

                continue; // Skip to the next filter
            }

            // Apply input filters dynamically for the columns that are listed in the filters array
            foreach ($filters as $filterKey => $filterDetails) {
                if ($request->has($filterKey) && is_array($request->input($filterKey))) {
                    // Use the correct column from the filters array for dynamic filtering
                    $query->whereIn($filterDetails['column'], $request->input($filterKey));
                }
            }

            // Apply pagination logic dynamically
            if ($details['paginate'] && (!$activeFilterKey || $key === $activeFilterKey)) {
                $results = $query
                    ->selectRaw("{$details['column']} as id, COUNT(*) as count")
                    ->groupBy("{$details['column']}")
                    ->paginate($perPage, ['*'], 'page', $page);
            } else {
                $results = $query
                    ->selectRaw("{$details['column']} as id, COUNT(*) as count")
                    ->groupBy("{$details['column']}")
                    ->get();
            }

            // Fetch related names in bulk
            $relatedNames = DB::table($details['table'])
                ->whereIn('id', $results->pluck('id'))
                ->pluck('name', 'id');

            // Map results to the response structure
            $response[$key] = $results->map(function ($item) use ($relatedNames, $details) {
                return [
                    "id" => $item->id,
                    'name' => $relatedNames[$item->id] ?? 'Unknown',
                    'count' => $item->count,
                ];
            });
        }

        // Return only the active filter's data if a specific filter is set
        if ($activeFilterKey) {
            return sendResponse(true, 200, ucfirst(str_replace('_', ' ', $activeFilterKey)) . ' Fetched Successfully!', [
                $activeFilterKey => $response[$activeFilterKey]
            ], 200);
        }

        return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
    } catch (\Exception $ex) {
        return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
    }
}

    /**
    * Get Vehicle Records Count.
    */
    public function filteredRecordsCount()
    {
        try {
            // Query the VehicleRecord model and filter by sale_date
            $vehicleRecords = VehicleRecord::selectRaw("
                COUNT(CASE WHEN sale_date IS NOT NULL THEN 1 END) as sale_records,
                COUNT(CASE WHEN sale_date IS NULL THEN 1 END) as no_sale_records,
                MAX(updated_at) as latest_update_time_utc
            ")
            ->first();

            // Prepare data for response
            $data = [
                'sale_records' => $vehicleRecords->sale_records,
                'no_sale_records' => $vehicleRecords->no_sale_records,
                'latest_update_time_utc' => $vehicleRecords->latest_update_time_utc,
            ];

            return sendResponse(true, 200, 'Filtered records fetched successfully!', $data, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    public function sendQuote(Request $request) {
        try {
            $details = [
                'name' => $request->name,
                'phone_number' => $request->phone_number,
                "contact_platform" => $request->contact_platform,
                "url" => $request->url
            ];
    
            Mail::to($request->receiver_email)->send(new SendQuoteMail($details));
            
            return sendResponse(true, 200, 'Quote Sent Successfully!', [], 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }
}