<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{VehicleRecord, VehicleType, Domain, Manufacturer, VehicleModel, Condition, Fuel, SellerType, DriveWheel, Transmission, DetailedTitle, Damage, Year, BuyNow};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            // Handling 'Domain'
            if ($request->has('domain_id')) {
                $query->where('domain_id', $request->input('domain_id'));
            }

            // Handling 'Buy Now'
            if ($request->has('buy_now')) {
                $buy_now_id = BuyNow::where('name', 'buyNowWithoutPrice')->pluck('id');
                $query->where('buy_now_id', $buy_now_id);
            }

            // Handling 'year_from' and 'year_to'
            if ($request->has('year_from') && $request->has('year_to')) {
                $query->whereBetween('year', [(int) $request->input('year_from'), (int) $request->input('year_to')]);
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

    public function filterAttributes(Request $request, $attribute)
    {
        $domainId = $request->input('domain_id');
        $ids = $request->input($attribute);
        try {
            if($attribute == 'years') {
                // Extract year range from the request
                $yearFrom = $request->input('year_from');
                $yearTo = $request->input('year_to');
                $yearIds = [];

                if ($yearFrom && $yearTo) {
                    $ids = Year::whereBetween('name', [$yearFrom, $yearTo])->pluck('id')->toArray();
                }
            }
            if($attribute == 'buy_now' && $request->buy_now == true) {
                $ids = BuyNow::where('name', 'buyNowWithoutPrice')->select('id')->pluck('id')->toArray();
            }

            // Define model-to-relation mappings
            $relationMappings = [
                'manufacturers' => [
                    'model' => Manufacturer::class,
                    'relations' => [
                        'vehicle_models' => 'manufacturer_vehicle_model',
                        'vehicle_types' => 'manufacturer_vehicle_type',
                        'conditions' => 'manufacturer_condition',
                        'fuels' => 'manufacturer_fuel',
                        'seller_types' => 'manufacturer_seller_type',
                        'drive_wheels' => 'manufacturer_drive_wheel',
                        'transmissions' => 'manufacturer_transmission',
                        'detailed_titles' => 'manufacturer_detailed_title',
                        'damages' => 'manufacturer_damage',
                        'years' => 'manufacturer_year',
                        'buyNows' => 'manufacturer_buy_now',
                    ],
                ],
                'vehicle_models' => [
                    'model' => VehicleModel::class,
                    'relations' => [
                        'manufacturers' => 'vehicle_model_manufacturer',
                        'vehicle_types' => 'vehicle_model_vehicle_type',
                        'conditions' => 'vehicle_model_condition',
                        'fuels' => 'vehicle_model_fuel',
                        'seller_types' => 'vehicle_model_seller_type',
                        'drive_wheels' => 'vehicle_model_drive_wheel',
                        'transmissions' => 'vehicle_model_transmission',
                        'detailed_titles' => 'vehicle_model_detailed_title',
                        'damages' => 'vehicle_model_damage',
                        'years' => 'vehicle_model_year',
                        'buyNows' => 'vehicle_model_buy_now',
                    ],
                ],
                'vehicle_types' => [
                    'model' => VehicleType::class,
                    'relations' => [
                        'manufacturers' => 'vehicle_type_manufacturer',
                        'vehicle_models' => 'vehicle_type_vehicle_model',
                        'conditions' => 'vehicle_type_condition',
                        'fuels' => 'vehicle_type_fuel',
                        'seller_types' => 'vehicle_type_seller_type',
                        'drive_wheels' => 'vehicle_type_drive_wheel',
                        'transmissions' => 'vehicle_type_transmission',
                        'detailed_titles' => 'vehicle_type_detailed_title',
                        'damages' => 'vehicle_type_damage',
                        'years' => 'vehicle_type_year',
                        'buyNows' => 'vehicle_type_buy_now',
                    ],
                ],
                'conditions' => [
                    'model' => Condition::class,
                    'relations' => [
                        'manufacturers' => 'condition_manufacturer',
                        'vehicle_models' => 'condition_vehicle_model',
                        'vehicle_types' => 'condition_vehicle_type',
                        'fuels' => 'condition_fuel',
                        'seller_types' => 'condition_seller_type',
                        'drive_wheels' => 'condition_drive_wheel',
                        'transmissions' => 'condition_transmission',
                        'detailed_titles' => 'condition_detailed_title',
                        'damages' => 'condition_damage',
                        'years' => 'condition_year',
                        'buyNows' => 'condition_buy_now',
                    ],
                ],
                'fuels' => [
                    'model' => Fuel::class,
                    'relations' => [
                        'manufacturers' => 'fuel_manufacturer',
                        'vehicle_models' => 'fuel_vehicle_model',
                        'vehicle_types' => 'fuel_vehicle_type',
                        'conditions' => 'fuel_condition',
                        'seller_types' => 'fuel_seller_type',
                        'drive_wheels' => 'fuel_drive_wheel',
                        'transmissions' => 'fuel_transmission',
                        'detailed_titles' => 'fuel_detailed_title',
                        'damages' => 'fuel_damage',
                        'years' => 'fuel_year',
                        'buyNows' => 'fuel_buy_now',
                    ],
                ],
                'seller_types' => [
                    'model' => SellerType::class,
                    'relations' => [
                        'manufacturers' => 'seller_type_manufacturer',
                        'vehicle_models' => 'seller_type_vehicle_model',
                        'vehicle_types' => 'seller_type_vehicle_type',
                        'conditions' => 'seller_type_condition',
                        'fuels' => 'seller_type_fuel',
                        'drive_wheels' => 'seller_type_drive_wheel',
                        'transmissions' => 'seller_type_transmission',
                        'detailed_titles' => 'seller_type_detailed_title',
                        'damages' => 'seller_type_damage',
                        'years' => 'seller_type_year',
                        'buyNows' => 'seller_type_buy_now',
                    ],
                ],
                'drive_wheels' => [
                    'model' => DriveWheel::class,
                    'relations' => [
                        'manufacturers' => 'drive_wheel_manufacturer',
                        'vehicle_models' => 'drive_wheel_vehicle_model',
                        'vehicle_types' => 'drive_wheel_vehicle_type',
                        'conditions' => 'drive_wheel_condition',
                        'fuels' => 'drive_wheel_fuel',
                        'seller_types' => 'drive_wheel_seller_type',
                        'transmissions' => 'drive_wheel_transmission',
                        'detailed_titles' => 'drive_wheel_detailed_title',
                        'damages' => 'drive_wheel_damage',
                        'years' => 'drive_wheel_year',
                        'buyNows' => 'drive_wheel_buy_now',
                    ],
                ],
                'transmissions' => [
                    'model' => Transmission::class,
                    'relations' => [
                        'manufacturers' => 'transmission_manufacturer',
                        'vehicle_models' => 'transmission_vehicle_model',
                        'vehicle_types' => 'transmission_vehicle_type',
                        'conditions' => 'transmission_condition',
                        'fuels' => 'transmission_fuel',
                        'seller_types' => 'transmission_seller_type',
                        'drive_wheels' => 'transmission_drive_wheel',
                        'detailed_titles' => 'transmission_detailed_title',
                        'damages' => 'transmission_damage',
                        'years' => 'transmission_year',
                        'buyNows' => 'transmission_buy_now',
                    ],
                ],
                'detailed_titles' => [
                    'model' => DetailedTitle::class,
                    'relations' => [
                        'manufacturers' => 'detailed_title_manufacturer',
                        'vehicle_models' => 'detailed_title_vehicle_model',
                        'vehicle_types' => 'detailed_title_vehicle_type',
                        'conditions' => 'detailed_title_condition',
                        'fuels' => 'detailed_title_fuel',
                        'seller_types' => 'detailed_title_seller_type',
                        'drive_wheels' => 'detailed_title_drive_wheel',
                        'transmissions' => 'detailed_title_transmission',
                        'damages' => 'detailed_title_damage',
                        'years' => 'detailed_title_year',
                        'buyNows' => 'detailed_title_buy_now',
                    ],
                ],
                'damages' => [
                    'model' => Damage::class,
                    'relations' => [
                        'manufacturers' => 'damage_manufacturer',
                        'vehicle_models' => 'damage_vehicle_model',
                        'vehicle_types' => 'damage_vehicle_type',
                        'conditions' => 'damage_condition',
                        'fuels' => 'damage_fuel',
                        'seller_types' => 'damage_seller_type',
                        'drive_wheels' => 'damage_drive_wheel',
                        'transmissions' => 'damage_transmission',
                        'detailed_titles' => 'damage_detailed_title',
                        'years' => 'damage_year',
                        'buyNows' => 'damage_buy_now',
                    ],
                ],
                'years' => [
                    'model' => Year::class,
                    'relations' => [
                        'manufacturers' => 'year_manufacturer',
                        'vehicle_models' => 'year_vehicle_model',
                        'vehicle_types' => 'year_vehicle_type',
                        'conditions' => 'year_condition',
                        'fuels' => 'year_fuel',
                        'seller_types' => 'year_seller_type',
                        'drive_wheels' => 'year_drive_wheel',
                        'transmissions' => 'year_transmission',
                        'detailed_titles' => 'year_detailed_title',
                        'damages' => 'year_damage',
                        'buyNows' => 'year_buy_now',
                    ],
                ],
                'buy_now' => [
                    'model' => BuyNow::class,
                    'relations' => [
                        'manufacturers' => 'buy_now_manufacturer',
                        'vehicle_models' => 'buy_now_vehicle_model',
                        'vehicle_types' => 'buy_now_vehicle_type',
                        'conditions' => 'buy_now_condition',
                        'fuels' => 'buy_now_fuel',
                        'seller_types' => 'buy_now_seller_type',
                        'drive_wheels' => 'buy_now_drive_wheel',
                        'transmissions' => 'buy_now_transmission',
                        'detailed_titles' => 'buy_now_detailed_title',
                        'damages' => 'buy_now_damage',
                        'years' => 'buy_now_year',
                    ],
                ],
            ];

            if (!isset($relationMappings[$attribute])) {
                return sendResponse(false, 400, 'Invalid attribute type.', '', 200);
            }

            $modelClass = $relationMappings[$attribute]['model'];
            $relations = $relationMappings[$attribute]['relations'];
            $attributes = array_fill_keys(array_keys($relations), []);
            
            // Handle the dynamic attribute explicitly for domain filtering and count
            if (in_array($attribute, ['manufacturers', 'vehicle_models', 'vehicle_types', 'conditions', 'fuels', 'seller_types', 'drive_wheels', 'transmissions', 'detailed_titles', 'damages'])) {
                // Define pivot table mappings for each attribute
                $pivotTableMappings = [
                    'manufacturers' => [
                        'pivotTable' => 'manufacturer_domain',
                        'foreignKey' => 'manufacturer_id',
                        'model' => Manufacturer::class,
                    ],
                    'vehicle_models' => [
                        'pivotTable' => 'vehicle_model_domain',
                        'foreignKey' => 'vehicle_model_id',
                        'model' => VehicleModel::class,
                    ],
                    'vehicle_types' => [
                        'pivotTable' => 'vehicle_type_domain',
                        'foreignKey' => 'vehicle_type_id',
                        'model' => VehicleType::class,
                    ],
                    'conditions' => [
                        'pivotTable' => 'condition_domain',
                        'foreignKey' => 'condition_id',
                        'model' => Condition::class,
                    ],
                    'fuels' => [
                        'pivotTable' => 'fuel_domain',
                        'foreignKey' => 'fuel_id',
                        'model' => Fuel::class,
                    ],
                    'seller_types' => [
                        'pivotTable' => 'seller_type_domain',
                        'foreignKey' => 'seller_type_id',
                        'model' => SellerType::class,
                    ],
                    'drive_wheels' => [
                        'pivotTable' => 'drive_wheel_domain',
                        'foreignKey' => 'drive_wheel_id',
                        'model' => DriveWheel::class,
                    ],
                    'transmissions' => [
                        'pivotTable' => 'transmission_domain',
                        'foreignKey' => 'transmission_id',
                        'model' => Transmission::class,
                    ],
                    'detailed_titles' => [
                        'pivotTable' => 'detailed_title_domain',
                        'foreignKey' => 'detailed_title_id',
                        'model' => DetailedTitle::class,
                    ],
                    'damages' => [
                        'pivotTable' => 'damage_domain',
                        'foreignKey' => 'damage_id',
                        'model' => Damage::class,
                    ],
                ];

                if (!isset($pivotTableMappings[$attribute])) {
                    return sendResponse(false, 400, 'Invalid attribute type.', '', 200);
                }

                $pivotTable = $pivotTableMappings[$attribute]['pivotTable'];
                $foreignKey = $pivotTableMappings[$attribute]['foreignKey'];
                $modelClass = $pivotTableMappings[$attribute]['model'];

                // Query the pivot table for the relevant data
                $data = \DB::table($pivotTable)
                    ->where('domain_id', $domainId)
                    ->select(
                        $foreignKey,
                        \DB::raw('CAST(SUM(count) AS UNSIGNED) as total_count')
                    )
                    ->groupBy($foreignKey)
                    ->get();

                $entityIds = $data->pluck($foreignKey)->toArray();

                // Fetch entities from the respective model
                $entities = $modelClass::whereIn('id', $entityIds)
                    ->get(['id', 'name']);

                // Map entities to include their count from the pivot table
                $attributes[$attribute] = $entities->map(function ($entity) use ($data, $foreignKey) {
                    $count = $data->firstWhere($foreignKey, $entity->id)->total_count ?? 0;
                    return [
                        'id' => $entity->id,
                        'name' => $entity->name,
                        'count' => $count, // Include count from pivot table
                    ];
                })->toArray();
            }

            // Define constraints for filtering by domain_id
            $relationConstraints = [];
            foreach ($relations as $relationName => $pivotTable) {
                $relationConstraints[$relationName] = function ($query) use ($domainId) {
                    $query->where('domain_id', $domainId);
                };
            }

            // Fetch data with domain_id filtering applied
            $modelClass::with($relationConstraints)
                ->whereIn('id', $ids)
                ->chunk(1000, function ($items) use (&$attributes, $relations) {
                    foreach ($items as $item) {
                        foreach ($relations as $relationName => $pivotTable) {
                            $relation = $item->$relationName;

                            if ($relation) {
                                foreach ($relation as $relatedItem) {
                                    $existingIndex = !empty($attributes[$relationName]) 
                                        ? array_search($relatedItem->id, array_column($attributes[$relationName], 'id')) 
                                        : false;

                                    if ($existingIndex !== false) {
                                        $attributes[$relationName][$existingIndex]['count'] += $relatedItem->pivot->count ?? 0;
                                    } else {
                                        $attributes[$relationName][] = [
                                            'id' => $relatedItem->id,
                                            'name' => $relatedItem->name,
                                            'count' => $relatedItem->pivot->count ?? 0,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                });

            return sendResponse(true, 200, 'Attributes fetched successfully.', $attributes, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }
}