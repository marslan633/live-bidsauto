<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{
    VehicleRecord, Manufacturer, VehicleModel, Generation, BodyType, Color,
    Transmission, DriveWheel, Fuel, Condition, Status, VehicleType, Domain,
    Engine, Seller, SellerType, Title, DetailedTitle, Damage, Image, Country,
    State, City, Location, SellingBranch, Year, BuyNow, Odometer, CacheKey, VehicleRecordArchived
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendQuoteMail;
use Illuminate\Support\Facades\Http;

class VehicleController extends Controller
{
    /**
    * Fetch Cars Information API.
    */
    public function vehicleInformations(Request $request)
    {
        try {
            // Determine the model based on the 'type' parameter
            $data_source = $request->input('data_source', 'active'); // Default to 'active'
            $model = $data_source === 'archived' ? VehicleRecordArchived::class : VehicleRecord::class;
        
            $query = $model::with([
                'manufacturer', 'vehicleModel', 'generation', 'bodyType', 'color', 'engine', 
                'transmission', 'driveWheel', 'vehicleType', 'fuel', 'status', 'seller', 
                'sellerType', 'titleRelation', 'detailedTitle', 'damageMain', 'damageSecond', 
                'condition', 'image', 'country', 'state', 'city', 'location', 'sellingBranch', 'buyNowRelation'
            ]);
            
            $query->whereNotNull('sale_date');
            // ->where('is_new', false);

            // Handling 'Domain'
            if ($request->has('domain_id')) {
                $query->whereIn('domain_id', $request->input('domain_id'));
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
 
            // Handling 'auction_date'
            if ($request->has('auction_date')) {
                $auctionDateInput = $request->input('auction_date');

                // Ensure it's an array with exactly two elements
                if (is_array($auctionDateInput) && count($auctionDateInput) === 2) {
                    [$auctionDateFrom, $auctionDateTo] = $auctionDateInput;

                    if ($auctionDateFrom && $auctionDateTo) {
                        // Keep Carbon instances for comparisons
                        $auctionDateFromCarbon = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom));
                        $auctionDateToCarbon = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateTo));

                        // Format for database query (string values)
                        $auctionDateFrom = $auctionDateFromCarbon->format('Y-m-d');
                        $auctionDateTo = $auctionDateToCarbon->format('Y-m-d');

                        // Compare using Carbon instances
                        if ($auctionDateFromCarbon->equalTo($auctionDateToCarbon)) {
                            // Same date, use whereDate
                            $query->whereDate('sale_date', $auctionDateFrom);
                        } else {
                            // Date range
                            $query->whereBetween('sale_date', [$auctionDateFrom, $auctionDateTo]);
                        }
                    } elseif ($auctionDateFrom && !$auctionDateTo) {
                        $auctionDate = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom))->format('Y-m-d');
                        $query->whereDate('sale_date', $auctionDate);
                    }
                } else {
                    return sendResponse(true, 400, "Invalid 'auction_date' format. Expecting an array with two elements.", [], 200);
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
            // Determine the model based on the 'type' parameter
            $data_source = $request->input('data_source', 'active'); // Default to 'active'
            $model = $data_source === 'archived' ? VehicleRecordArchived::class : VehicleRecord::class;

            $query = $model::with([
                'manufacturer', 'vehicleModel', 'generation', 'bodyType', 'color', 'engine', 
                'transmission', 'driveWheel', 'vehicleType', 'fuel', 'status', 'seller', 
                'sellerType', 'titleRelation', 'detailedTitle', 'damageMain', 'damageSecond', 
                'condition', 'image', 'country', 'state', 'city', 'location', 'sellingBranch', 'buyNowRelation'
            ]);

            // If querying from archived data and is_history is true, include SaleAuctionHistory
            $includeHistory = filter_var($request->input('is_history', false), FILTER_VALIDATE_BOOLEAN);
            if ($data_source === 'archived' && $includeHistory) {
                $query->with('saleHistories.domain', 'saleHistories.status', 'saleHistories.seller');
            }
            
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
    * Filter Attributes and Manage Counts API.
    */ 
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
            'buy_now' => ['column' => 'buy_now_id', 'relation' => 'buyNowRelation', 'table' => 'buy_nows', 'paginate' => true],
        ];

        $response = [];
        $page = $request->input('page'); // Default to page 1 if not provided
        $perPage = $request->input('size'); // Default to 20 items per page

        // Search parameters
        $searchAttribute = $request->input('search_attribute');
        $searchValue = $request->input('search_value');

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

            // Determine the model based on the 'type' parameter
            $data_source = $request->input('data_source', 'active'); // Default to 'active'
            $model = $data_source === 'archived' ? VehicleRecordArchived::class : VehicleRecord::class;

            $query = $model::query()->whereNotNull('sale_date');

            // Apply domain filter if provided
            if ($request->has('domain_id')) {
                $query->whereIn('domain_id', $request->input('domain_id'));
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

            // Handling 'auction_date'
            if ($request->has('auction_date')) {
                $auctionDateInput = $request->input('auction_date');

                // Ensure it's an array with exactly two elements
                if (is_array($auctionDateInput) && count($auctionDateInput) === 2) {
                    [$auctionDateFrom, $auctionDateTo] = $auctionDateInput;

                    if ($auctionDateFrom && $auctionDateTo) {
                        // Keep Carbon instances for comparisons
                        $auctionDateFromCarbon = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom));
                        $auctionDateToCarbon = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateTo));

                        // Format for database query (string values)
                        $auctionDateFrom = $auctionDateFromCarbon->format('Y-m-d');
                        $auctionDateTo = $auctionDateToCarbon->format('Y-m-d');

                        // Compare using Carbon instances
                        if ($auctionDateFromCarbon->equalTo($auctionDateToCarbon)) {
                            // Same date, use whereDate
                            $query->whereDate('sale_date', $auctionDateFrom);
                        } else {
                            // Date range
                            $query->whereBetween('sale_date', [$auctionDateFrom, $auctionDateTo]);
                        }
                    } elseif ($auctionDateFrom && !$auctionDateTo) {
                        $auctionDate = \Carbon\Carbon::createFromFormat('Y-m-d', trim($auctionDateFrom))->format('Y-m-d');
                        $query->whereDate('sale_date', $auctionDate);
                    }
                } else {
                    return sendResponse(true, 400, "Invalid 'auction_date' format. Expecting an array with two elements.", [], 200);
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
                    ->groupBy("{$details['column']}");

                // Apply pagination if parameters exist
                if (!empty($perPage) && !empty($page)) {
                    $existingResults = $existingResults->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $existingResults = $existingResults->get();
                }


                // Fetch related names in bulk
                $relatedNames = DB::table($details['table'])
                    ->whereIn('id', $existingResults->pluck('id'))
                    ->pluck('name', 'id');


                // Map results to the response structure
                $response[$key] = $existingResults->map(function ($item) use ($relatedNames, $details) {
                    return [
                        "id" => $item->id,
                        'name' => $relatedNames[$item->id] ?? 'unknown',
                        'count' => $item->count,
                    ];
                })->sortBy('name')->values();

                continue; // Skip to the next filter
            }

            // If search_attribute is set and valid, perform search
            if ($searchAttribute && in_array($searchAttribute, $validListings) && $searchValue) {
                $cloneQuery = clone $query;

                // Fetch filtered results
                $filteredResults = $cloneQuery->whereHas($filters[$searchAttribute]['relation'], function ($query) use ($searchValue) {
                    $query->where('name', 'LIKE', "%$searchValue%");
                })->select("{$filters[$searchAttribute]['column']} as id", DB::raw("COUNT(*) as count"))
                ->groupBy("{$filters[$searchAttribute]['column']}")
                ->paginate($perPage, ['*'], 'page', $page);

                // Fetch related names in bulk
                $relatedNames = DB::table($filters[$searchAttribute]['table'])
                    ->whereIn('id', $filteredResults->pluck('id'))
                    ->pluck('name', 'id');

                // Map results to the response structure
                $response[$searchAttribute] = $filteredResults->map(function ($item) use ($relatedNames) {
                    return [
                        "id" => $item->id,
                        'name' => $relatedNames[$item->id] ?? 'unknown',
                        'count' => $item->count,
                    ];
                })->sortBy('name')->values();
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
                    'name' => $relatedNames[$item->id] ?? 'unknown',
                    'count' => $item->count,
                ];
            })->sortBy('name')->values();

        }

        // Return only the active filter's data if a specific filter is set
        if ($activeFilterKey) {
            return sendResponse(true, 200, ucfirst(str_replace('_', ' ', $activeFilterKey)) . ' Fetched Successfully!', [
                $activeFilterKey => $response[$activeFilterKey]
            ], 200);
        }

        $finalResult = $response; // This is first query result and accurate

        return sendResponse(true, 200, 'Attributes Fetched Successfully!', $finalResult, 200);
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

            $vehicleRecordArchiveds = VehicleRecordArchived::count();

            // Prepare data for response
            $data = [
                'sale_records' => $vehicleRecords->sale_records,
                'no_sale_records' => $vehicleRecords->no_sale_records,
                'archived_vehicle_record' => $vehicleRecordArchiveds,
                'latest_update_time_utc' => $vehicleRecords->latest_update_time_utc,
            ];

            return sendResponse(true, 200, 'Filtered records fetched successfully!', $data, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    /**
     * Send Quote API
     */
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

    /**
     * Fetch Cron Job History Records API
     */
    public function cronJobHistory(Request $request)
    {
        try {
            // Pagination
            $page = $request->input('page', 1);
            $size = $request->input('size', 10);
            
            // Fetch the latest record(s) from the cron_run_history table
            $history = DB::table('cron_run_history')->orderBy('id', 'desc')
                    ->skip(($page - 1) * $size)->take($size)->get();

            return sendResponse(true, 200, 'Filtered records fetched successfully!', $history, 200);
        } catch (\Exception $ex) {
            // Handle any exception and return a response
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    /**
     * Fetch Cache Key History Records API
     */
    public function cacheKeyHistory()
    {
        try {
            // Fetch the latest record(s) from the cron_run_history table
            $history = CacheKey::orderBy('id', 'desc')->get();

            return sendResponse(true, 200, 'Cache Keys fetched successfully!', $history, 200);
        } catch (\Exception $ex) {
            // Handle any exception and return a response
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    /**
     * Below Function to get those record who has highest value of total_records field.
     */
    public function getMaxRecord(Request $request)
    {
        // Query to get the record(s) with the maximum value in total_records
        $records = DB::table('cron_run_history')
            ->where('total_records', function ($query) {
                $query->select(DB::raw('MAX(total_records)'))
                      ->from('cron_run_history');
            })
            ->get();

        // Return the result as JSON
        return response()->json([
            'status' => true,
            'data' => $records
        ]);
    }
    public function testApi(Request $request) {
        
        // Get the last cron job record
        $lastCron = DB::table('cron_run_history')
            ->where('cron_name', 'process_vehicle_data')
            ->where('status', 'success')
            ->latest('start_time')
            ->first();

        $minutes = 20; // Default minutes value

        if ($lastCron && $lastCron->end_time) {
            // Convert end_time to Carbon instance
            $endTime = Carbon::parse($lastCron->end_time);
            
            // Get the difference in minutes (ensure it's a non-negative integer)
            $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));
            
            // Apply the new conditions
            if ($timeDifference > 20) {
                $minutes = $timeDifference + 10;
            } elseif ($timeDifference === 20) {
                $minutes = $timeDifference + 5;
            }
        }

        return $minutes;
        // $now = now();
        // $sale_date = "2025-02-05T15:00:00.000000Z";
        
        // if($now < $sale_date) {
        //     return "now < sale_date".now();
        // } else if($now > $sale_date) {
        //     return "now > sale_date".now();
        // }
        // $expiredRecords = VehicleRecord::where('sale_date', '>', now())->take(100)->get();

        // return $expiredRecords;

        $expiredRecords = VehicleRecord::whereRaw("STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ') < ?", [now()])
            ->take(100)
            ->get();
        return $expiredRecords;
    }
}