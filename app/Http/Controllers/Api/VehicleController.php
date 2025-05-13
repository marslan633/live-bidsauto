<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendQuoteMail;
use App\Models\BuyNow;
use App\Models\CacheKey;
use App\Models\CronRunHistory;
use App\Models\Domain;
use App\Models\Odometer;
use App\Models\Status;
use App\Models\VehicleArchivedApiData;
use App\Models\VehicleProcessCachedApiData;
use App\Models\VehicleRecord;
use App\Models\VehicleRecordArchived;
use App\Models\Year;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VehicleController extends Controller
{
    public function deleteMyVehicle($id)
    {
        try {
            $deleted = VehicleProcessCachedApiData::where('_id', $id)->delete();
            if ($deleted) {
                return sendResponse(true, 200, 'Record Deleted Successfully', null, 200);
            } else {
                return sendResponse(false, 404, 'Record Not Found', null, 404);
            }
        } catch (\Exception $ex) {
            Log::info('Delete Cached Data Error', ['data' => json_encode($ex->getMessage())]);

            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    public function deleteMyArchiveVehicle($id)
    {
        try {
            $deleted = VehicleArchivedApiData::where('_id', $id)->delete();
            if ($deleted) {
                return sendResponse(true, 200, 'Record Deleted Successfully', null, 200);
            } else {
                return sendResponse(false, 404, 'Record Not Found', null, 404);
            }
        } catch (\Exception $ex) {
            Log::info('Delete Cached Data Error', ['data' => json_encode($ex->getMessage())]);

            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    public function destroy_archived($id)
    {
        try {
            $deleted = VehicleArchivedApiData::where('_id', $id)->delete();
            if ($deleted) {
                return sendResponse(true, 200, 'Record Deleted Successfully', null, 200);
            } else {
                return sendResponse(false, 404, 'Record Not Found', null, 404);
            }
        } catch (\Exception $ex) {
            Log::info('Delete Archived Data Error', ['data' => json_encode($ex->getMessage())]);

            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    public function getVechiclesForDatabase()
    {
        try {
            $data = VehicleProcessCachedApiData::orderBy('created_at', 'desc')->paginate(intval(config('app.per_page_vehicle_data')));

            return sendResponse(true, 200, 'Vehicles Detail Fetched Successfully!', $data, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    public function getArchivedVechiclesForDatabase()
    {
        try {
            $data = VehicleArchivedApiData::orderBy('created_at', 'desc')->paginate(intval(config('app.per_page_archived_data')));

            return sendResponse(true, 200, 'Archived Vehicles Detail Fetched Successfully!', $data, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }


    public function vehicleInformationsWithFilters(Request $request)
    {
        try {
            $client = app('ElasticsearchKvmFour');

            $index = $request->input('data_source', 'active') === 'archived'
                ? 'vehicle_record_archiveds'
                : 'vehicle_records';

            $page = (int) $request->input('page', 1);
            $size = (int) $request->input('size', 10);
            $from = ($page - 1) * $size;

            $filters = [
                'manufacturers' => ['column' => 'manufacturer_id', 'relation' => 'manufacturer', 'table' => 'manufacturers'],
                'vehicle_models' => ['column' => 'vehicle_model_id', 'relation' => 'vehicleModel', 'table' => 'vehicle_models'],
                'vehicle_types' => ['column' => 'vehicle_type_id', 'relation' => 'vehicleType', 'table' => 'vehicle_types'],
                'conditions' => ['column' => 'condition_id', 'relation' => 'condition', 'table' => 'conditions'],
                'fuels' => ['column' => 'fuel_id', 'relation' => 'fuel', 'table' => 'fuels'],
                'seller_types' => ['column' => 'seller_type_id', 'relation' => 'sellerType', 'table' => 'seller_types'],
                'drive_wheels' => ['column' => 'drive_wheel_id', 'relation' => 'driveWheel', 'table' => 'drive_wheels'],
                'transmissions' => ['column' => 'transmission_id', 'relation' => 'transmission', 'table' => 'transmissions'],
                'detailed_titles' => ['column' => 'detailed_title_id', 'relation' => 'detailedTitle', 'table' => 'detailed_titles'],
                'damages' => ['column' => 'damage_id', 'relation' => 'damageMain', 'table' => 'damages'],
                'buy_now' => ['column' => 'buy_now_id', 'relation' => 'buyNowRelation', 'table' => 'buy_nows'],
            ];

            $searchAttribute = $request->input('search_attribute');
            $searchValue = $request->input('search_value');
            $currentHitAttribute = $request->input('current_hit_attribute');
            $listing = $request->input('listing');
            $validListings = array_keys($filters);
            $activeFilterKey = in_array($listing, $validListings) ? $listing : null;

            $must = [['exists' => ['field' => 'sale_date']]];

            if ($request->has('domain_id')) {
                $must[] = ['terms' => ['domain_id' => (array) $request->input('domain_id')]];
            }

            if ($request->has('buy_now')) {
                $buyNow = $request->input('buy_now');
                if ($buyNow === true || $buyNow === 'true') {
                    $must[] = ['term' => ['buy_now_id' => BuyNow::where('name', 'buyNowWithPrice')->value('id')]];
                } else {
                    $must[] = ['terms' => ['buy_now_id' => BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])->pluck('id')->toArray()]];
                }
            }

            if ($request->has(['year_from', 'year_to'])) {
                $must[] = ['range' => ['year' => [
                    'gte' => (int) $request->input('year_from'),
                    'lte' => (int) $request->input('year_to')
                ]]];
            }

            if ($request->has(['odometer_min', 'odometer_max'])) {
                $must[] = ['range' => ['odometer_mi' => [
                    'gte' => (int) str_replace(',', '', $request->input('odometer_min')),
                    'lte' => (int) str_replace(',', '', $request->input('odometer_max')),
                ]]];
            }

            if ($request->has('auction_date')) {
                $dates = $request->input('auction_date');
                if (is_array($dates) && count($dates) === 2) {
                    $must[] = ['range' => ['sale_date' => [
                        'gte' => Carbon::parse($dates[0])->format('Y-m-d'),
                        'lte' => Carbon::parse($dates[1])->format('Y-m-d')
                    ]]];
                }
            }

            foreach ($filters as $key => $config) {
                if ($request->has($key) && is_array($request->input($key))) {
                    $must[] = ['terms' => [$config['column'] => array_map('intval', $request->input($key))]];
                }
            }

            $sort = [
                [
                    '_script' => [
                        'type' => 'number',
                        'script' => [
                            'source' => "doc['sale_date'].value.toInstant().toEpochMilli() >= params.date ? 1 : 0",
                            'params' => ['date' => Carbon::now()->timestamp * 1000],
                            'lang' => 'painless'
                        ],
                        'order' => 'desc'
                    ]
                ],
                ['sale_date' => $request->input('sale_date_order', 'sooner') === 'farthest' ? 'desc' : 'asc']
            ];

            $params = [
                'index' => $index,
                'body' => [
                    'from' => $from,
                    'size' => $size,
                    'query' => ['bool' => ['must' => $must]],
                    'sort' => $sort,
                    'track_total_hits' => true
                ]
            ];

            $results = $client->search($params);

            $vehicles = collect($results['hits']['hits'])->map(fn($hit) => $hit['_source']);
            $count = $results['hits']['total']['value'] ?? 0;

            // filter attributes
            $responseFilters = [];

            foreach ($filters as $key => $config) {
                if ($activeFilterKey && $key !== $activeFilterKey) continue;

                $localMust = $must;

                foreach ($filters as $filterKey => $filterDetails) {
                    if ($filterKey === $key) continue;
                    if ($request->has($filterKey) && is_array($request->input($filterKey))) {
                        $localMust[] = ['terms' => [$filterDetails['column'] => $request->input($filterKey)]];
                    }
                }

                if ($searchAttribute === $key && $searchValue) {
                    $nameMatches = DB::table($config['table'])
                        ->where('name', 'LIKE', "%{$searchValue}%")
                        ->pluck('id')
                        ->toArray();

                    $localMust[] = ['terms' => [$config['column'] => $nameMatches]];
                }

                $aggParams = [
                    'index' => $index,
                    'body' => [
                        'size' => 0,
                        'query' => ['bool' => ['must' => $localMust]],
                        'aggs' => [
                            $key => [
                                'terms' => [
                                    'field' => $config['column'],
                                    'size' => 1000
                                ]
                            ]
                        ]
                    ]
                ];

                $aggResults = $client->search($aggParams);
                $buckets = $aggResults['aggregations'][$key]['buckets'] ?? [];
                $names = DB::table($config['table'])->pluck('name', 'id');

                $responseFilters[$key] = collect($buckets)->map(function ($bucket) use ($names) {
                    return [
                        'id' => $bucket['key'],
                        'name' => $names[$bucket['key']] ?? 'unknown',
                        'count' => $bucket['doc_count'],
                    ];
                })->sortBy('name')->values();
            }

            return sendResponse(true, 200, 'Vehicle Informations & Filters Fetched Successfully!', [
                'count' => $count,
                'data' => $vehicles,
                'filters' => $responseFilters
            ], 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 500);
        }
    }

    public function vehicleInformations(Request $request)
    {
        try {
            $client = app('ElasticsearchKvmFour');

            $index = $request->input('data_source', 'active') === 'archived'
                ? 'vehicle_record_archiveds'
                : 'vehicle_records';

            $page = (int) $request->input('page', 1);
            $size = (int) $request->input('size', 10);
            $from = ($page - 1) * $size;

            $must = [['exists' => ['field' => 'sale_date']]];

            // Domain Filter
            if ($request->has('domain_id')) {
                $domainIds = array_map('intval', (array) $request->input('domain_id'));
                $must[] = ['terms' => ['domain_id' => $domainIds]];
            }

            // Buy Now Filter
            if ($request->has('buy_now')) {
                $buyNow = $request->input('buy_now');
                if ($buyNow === true || $buyNow === 'true' || $buyNow === 1 || $buyNow === '1') {
                    $buyNowId = BuyNow::where('name', 'buyNowWithPrice')->value('id');
                    $must[] = ['term' => ['buy_now_id' => (int) $buyNowId]];
                } else {
                    $buyNowIds = BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])->pluck('id')->map(fn($i) => (int) $i)->toArray();
                    $must[] = ['terms' => ['buy_now_id' => $buyNowIds]];
                }
            }

            // Year range
            if ($request->has(['year_from', 'year_to'])) {
                $must[] = ['range' => [
                    'year' => [
                        'gte' => (int) $request->input('year_from'),
                        'lte' => (int) $request->input('year_to'),
                    ]
                ]];
            }

            // Odometer range
            if ($request->has(['odometer_min', 'odometer_max'])) {
                $must[] = ['range' => [
                    'odometer_mi' => [
                        'gte' => (int) str_replace(',', '', $request->input('odometer_min')),
                        'lte' => (int) str_replace(',', '', $request->input('odometer_max')),
                    ]
                ]];
            }

            // Auction date range
            if ($request->has('auction_date')) {
                $dates = $request->input('auction_date');
                if (is_array($dates) && count($dates) === 2) {
                    $must[] = ['range' => [
                        'sale_date' => [
                            'gte' => \Carbon\Carbon::parse($dates[0])->format('Y-m-d'),
                            'lte' => \Carbon\Carbon::parse($dates[1])->format('Y-m-d'),
                        ]
                    ]];
                }
            }

            // Dynamic Filters
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
                'damages' => 'damage_id',
            ];

            foreach ($filters as $key => $column) {
                if ($request->has($key) && is_array($request->input($key))) {
                    $values = array_map('intval', $request->input($key));
                    $must[] = ['terms' => [$column => $values]];
                }
            }

            // Sorting
            $currentDate = \Carbon\Carbon::now()->toDateString();
            $currentDateMillis = \Carbon\Carbon::parse($currentDate)->timestamp * 1000;
            $saleDateOrder = $request->input('sale_date_order', 'sooner');

            $sort = [
                [
                    '_script' => [
                        'type' => 'number',
                        'script' => [
                            'source' => "doc['sale_date'].value.toInstant().toEpochMilli() >= params.date ? 1 : 0",
                            'params' => ['date' => $currentDateMillis],
                            'lang' => 'painless',
                        ],
                        'order' => 'desc'
                    ]
                ],
                ['sale_date' => $saleDateOrder === 'farthest' ? 'desc' : 'asc']
            ];

            // Final ES Query
            $params = [
                'index' => $index,
                'body' => [
                    'from' => $from,
                    'size' => $size,
                    'query' => ['bool' => ['must' => $must]],
                    'sort' => $sort,
                    'track_total_hits' => true
                ]
            ];




            $results = $client->search($params);

            $vehicles = collect($results['hits']['hits'])->map(fn($hit) => $hit['_source']);
            $count = $results['hits']['total']['value'] ?? 0;

            return sendResponse(true, 200, 'Vehicle Informations Fetched Successfully!', [
                'count' => $count,
                'data' => $vehicles
            ], 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 500);
        }
    }

    /**
     * Fetch Cars Information API.
     */
    public function oldVehicleInformations(Request $request)
    {
        try {
            // Determine the model based on the 'type' parameter
            $data_source = $request->input('data_source', 'active'); // Default to 'active'
            $model = $data_source === 'archived' ? VehicleRecordArchived::class : VehicleRecord::class;

            $query = $model::with([
                'manufacturer',
                'vehicleModel',
                'generation',
                'bodyType',
                'color',
                'engine',
                'transmission',
                'driveWheel',
                'vehicleType',
                'fuel',
                'status',
                'seller',
                'sellerType',
                'titleRelation',
                'detailedTitle',
                'damageMain',
                'damageSecond',
                'condition',
                'image',
                'country',
                'state',
                'city',
                'location',
                'sellingBranch',
                'buyNowRelation',
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
                    } elseif ($auctionDateFrom && ! $auctionDateTo) {
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
                'damages' => 'damage_id',
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
                'data' => $vehicleInformations,
            ];

            return sendResponse(true, 200, 'Vehicle Informations Fetched Successfully!', $response, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
        }
    }

    /**
         * Search vehicle information records throught lot_id or vin.
         */
        /**
     * Search vehicle information records through lot_id or vin using Elasticsearch.
     */
    public function searchVehicle(Request $request, $id)
{
    try {
        // Determine the index based on the 'data_source' parameter
        $data_source = $request->input('data_source', 'active'); // Default to 'active'
        $index = $data_source === 'archived' ? 'vehicle_record_archiveds' : 'vehicle_records';
        $client = app('ElasticsearchKvmFour');

        // Debug Log: Check Index
        Log::info('Search Index: ', ['index' => $index]);

        // Base query for vehicle records
        $query = [
            'index' => $index,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => []
                    ]
                ],
                'size' => 1
            ]
        ];

        // Search by type (lot_id or vin)
        if ($request->has('type') && $request->type === 'lot_id') {
            $query['body']['query']['bool']['must'][] = ['match' => ['lot_id' => $id]];
        } elseif ($request->type === 'vin') {
            $query['body']['query']['bool']['must'][] = ['match' => ['vin' => $id]];
        }

        // Debug Log: Search Query
        Log::info('Search Query: ', ['query' => $query]);

        // Execute the main search query
        $response = $client->search($query);

        // Debug Log: Search Response
        Log::info('Search Response: ', ['response' => $response]);

        if (!empty($response['hits']['hits'])) {
            $record = $response['hits']['hits'][0]['_source'];

            // If is_history is true, we fetch sale_auction_histories data
            $includeHistory = filter_var($request->input('is_history', false), FILTER_VALIDATE_BOOLEAN);
            $saleHistories = [];

            if ($includeHistory) {
                // Query sale_auction_histories index to get auction history matching vin
                $saleHistoryQuery = [
                    'index' => 'sale_auction_histories',
                    'body' => [
                        'query' => [
                            'match' => [
                                'vin' => $record['vin']
                            ]
                        ],
                        'size' => 10  // Adjust size if needed
                    ]
                ];

                $saleHistoryResponse = $client->search($saleHistoryQuery);

                // If we have sale_auction_histories, include them in the result
                if (!empty($saleHistoryResponse['hits']['hits'])) {
                    $saleHistories = collect($saleHistoryResponse['hits']['hits'])->map(fn($hit) => $hit['_source'])->toArray();
                }
            }

            // Add the sale_auction_histories to the result, even if it's an empty array
            $record['sale_auction_histories'] = $saleHistories;

            return sendResponse(true, 200, 'Car Detail Fetched Successfully!', $record, 200);
        } else {
            return sendResponse(false, 404, 'Not Found', 'Car detail not found', 200);
        }
    } catch (\Exception $ex) {
        Log::info('Search Error: ', ['data' => $ex->getMessage()]);
        return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 200);
    }
}



    /**
         * Search vehicle information records throught lot_id or vin.
         */
        /**
     * Search vehicle information records through lot_id or vin using Elasticsearch.
     */
    public function searchVehicleOld(Request $request, $id)
    {
        try {
            // Determine the model based on the 'type' parameter
            $data_source = $request->input('data_source', 'active'); // Default to 'active'
            $model = $data_source === 'archived' ? VehicleRecordArchived::class : VehicleRecord::class;

            $query = $model::with([
                'manufacturer',
                'vehicleModel',
                'generation',
                'bodyType',
                'color',
                'engine',
                'transmission',
                'driveWheel',
                'vehicleType',
                'fuel',
                'status',
                'seller',
                'sellerType',
                'titleRelation',
                'detailedTitle',
                'damageMain',
                'damageSecond',
                'condition',
                'image',
                'country',
                'state',
                'city',
                'location',
                'sellingBranch',
                'buyNowRelation',
            ]);

            // If querying from archived data and is_history is true, include SaleAuctionHistory
            $includeHistory = filter_var($request->input('is_history', false), FILTER_VALIDATE_BOOLEAN);
            // if ($data_source === 'archived' && $includeHistory) {
            //     $query->with('saleHistories.domain', 'saleHistories.status', 'saleHistories.seller');
            // }
            if ($includeHistory) {
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
     * Search vehicle information records throught lot_id or vin.
     */
    public function oldsearchVehicle(Request $request, $id)
    {
        try {
            // Determine the model based on the 'type' parameter
            $data_source = $request->input('data_source', 'active'); // Default to 'active'
            $model = $data_source === 'archived' ? VehicleRecordArchived::class : VehicleRecord::class;

            $query = $model::with([
                'manufacturer',
                'vehicleModel',
                'generation',
                'bodyType',
                'color',
                'engine',
                'transmission',
                'driveWheel',
                'vehicleType',
                'fuel',
                'status',
                'seller',
                'sellerType',
                'titleRelation',
                'detailedTitle',
                'damageMain',
                'damageSecond',
                'condition',
                'image',
                'country',
                'state',
                'city',
                'location',
                'sellingBranch',
                'buyNowRelation',
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



    public function onefilterAttributes(Request $request)
    {
        try {
            $client = app('ElasticsearchKvmFour');

            $index = $request->input('data_source', 'active') === 'archived'
                ? 'vehicle_record_archiveds'
                : 'vehicle_records';

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

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('size', 10);
            $from = ($page - 1) * $perPage;

            $searchAttribute = $request->input('search_attribute');
            $searchValue = $request->input('search_value');
            $currentHitAttribute = $request->input('current_hit_attribute');
            $listing = $request->input('listing');

            $validListings = array_keys($filters);
            $activeFilterKey = in_array($listing, $validListings) ? $listing : null;

            $must = [['exists' => ['field' => 'sale_date']]];

            if ($request->has('domain_id')) {
                $must[] = ['terms' => ['domain_id' => $request->input('domain_id')]];
            }

            if ($request->has('buy_now')) {
                $buyNow = $request->input('buy_now');
                if ($buyNow === true || $buyNow === 'true') {
                    $must[] = ['term' => ['buy_now_id' => BuyNow::where('name', 'buyNowWithPrice')->value('id')]];
                } else {
                    $must[] = ['terms' => ['buy_now_id' => BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])->pluck('id')->toArray()]];
                }
            }

            if ($request->has(['year_from', 'year_to'])) {
                $must[] = ['range' => ['year' => ['gte' => (int) $request->input('year_from'), 'lte' => (int) $request->input('year_to')]]];
            }

            if ($request->has(['odometer_min', 'odometer_max'])) {
                $must[] = ['range' => ['odometer_mi' => [
                    'gte' => (int) str_replace(',', '', $request->input('odometer_min')),
                    'lte' => (int) str_replace(',', '', $request->input('odometer_max'))
                ]]];
            }

            if ($request->has('auction_date')) {
                $dates = $request->input('auction_date');
                if (is_array($dates) && count($dates) === 2) {
                    $must[] = ['range' => ['sale_date' => [
                        'gte' => Carbon::parse($dates[0])->format('Y-m-d'),
                        'lte' => Carbon::parse($dates[1])->format('Y-m-d'),
                    ]]];
                }
            }

            foreach ($filters as $key => $config) {
                if ($activeFilterKey && $key !== $activeFilterKey) continue;

                $localMust = $must;

                foreach ($filters as $filterKey => $filterDetails) {
                    if ($filterKey === $key) continue;
                    if ($request->has($filterKey) && is_array($request->input($filterKey))) {
                        $localMust[] = ['terms' => [$filterDetails['column'] => $request->input($filterKey)]];
                    }
                }

                $params = [
                    'index' => $index,
                    'body' => [
                        'from' => 0,
                        'size' => 0,
                        'query' => ['bool' => ['must' => $localMust]],
                        'aggs' => [
                            $key => [
                                'terms' => [
                                    'field' => $config['column'],
                                    'size' => 1000
                                ]
                            ]
                        ]
                    ]
                ];

                $results = $client->search($params);

                $buckets = $results['aggregations'][$key]['buckets'] ?? [];

                $names = DB::table($config['table'])->pluck('name', 'id');

                $response[$key] = collect($buckets)->map(function ($bucket) use ($names) {
                    return [
                        'id' => $bucket['key'],
                        'name' => $names[$bucket['key']] ?? 'unknown',
                        'count' => $bucket['doc_count'],
                    ];
                })->sortBy('name')->values();
            }

            if ($activeFilterKey) {
                return sendResponse(true, 200, ucfirst(str_replace('_', ' ', $activeFilterKey)) . ' Fetched Successfully!', [
                    $activeFilterKey => $response[$activeFilterKey],
                ], 200);
            }

            return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 500);
        }
    }

    public function filterAttributes(Request $request)
    {
        try {
            $client = app('ElasticsearchKvmFour');

            $index = $request->input('data_source', 'active') === 'archived'
                ? 'vehicle_record_archiveds'
                : 'vehicle_records';

            $filters = [
                'manufacturers' => ['column' => 'manufacturer_id', 'relation' => 'manufacturer', 'table' => 'manufacturers'],
                'vehicle_models' => ['column' => 'vehicle_model_id', 'relation' => 'vehicleModel', 'table' => 'vehicle_models'],
                'vehicle_types' => ['column' => 'vehicle_type_id', 'relation' => 'vehicleType', 'table' => 'vehicle_types'],
                'conditions' => ['column' => 'condition_id', 'relation' => 'condition', 'table' => 'conditions'],
                'fuels' => ['column' => 'fuel_id', 'relation' => 'fuel', 'table' => 'fuels'],
                'seller_types' => ['column' => 'seller_type_id', 'relation' => 'sellerType', 'table' => 'seller_types'],
                'drive_wheels' => ['column' => 'drive_wheel_id', 'relation' => 'driveWheel', 'table' => 'drive_wheels'],
                'transmissions' => ['column' => 'transmission_id', 'relation' => 'transmission', 'table' => 'transmissions'],
                'detailed_titles' => ['column' => 'detailed_title_id', 'relation' => 'detailedTitle', 'table' => 'detailed_titles'],
                'damages' => ['column' => 'damage_id', 'relation' => 'damageMain', 'table' => 'damages'],
                'buy_now' => ['column' => 'buy_now_id', 'relation' => 'buyNowRelation', 'table' => 'buy_nows'],
            ];

            $searchAttribute = $request->input('search_attribute');
            $searchValue = $request->input('search_value');
            $currentHitAttribute = $request->input('current_hit_attribute');
            $listing = $request->input('listing');
            $validListings = array_keys($filters);
            $activeFilterKey = in_array($listing, $validListings) ? $listing : null;

            $must = [['exists' => ['field' => 'sale_date']]];

            if ($request->has('domain_id')) {
                $must[] = ['terms' => ['domain_id' => $request->input('domain_id')]];
            }

            if ($request->has('buy_now')) {
                $buyNow = $request->input('buy_now');
                if ($buyNow === true || $buyNow === 'true') {
                    $must[] = ['term' => ['buy_now_id' => BuyNow::where('name', 'buyNowWithPrice')->value('id')]];
                } else {
                    $must[] = ['terms' => ['buy_now_id' => BuyNow::whereIn('name', ['buyNowWithoutPrice', 'buyNowWithPrice'])->pluck('id')->toArray()]];
                }
            }

            if ($request->has(['year_from', 'year_to'])) {
                $must[] = ['range' => ['year' => ['gte' => (int) $request->year_from, 'lte' => (int) $request->year_to]]];
            }

            if ($request->has(['odometer_min', 'odometer_max'])) {
                $must[] = ['range' => ['odometer_mi' => [
                    'gte' => (int) str_replace(',', '', $request->input('odometer_min')),
                    'lte' => (int) str_replace(',', '', $request->input('odometer_max')),
                ]]];
            }

            if ($request->has('auction_date')) {
                $dates = $request->input('auction_date');
                if (is_array($dates) && count($dates) === 2) {
                    $must[] = ['range' => ['sale_date' => [
                        'gte' => Carbon::parse($dates[0])->format('Y-m-d'),
                        'lte' => Carbon::parse($dates[1])->format('Y-m-d'),
                    ]]];
                }
            }

            $response = [];

            foreach ($filters as $key => $config) {
                if ($activeFilterKey && $key !== $activeFilterKey) continue;

                $localMust = $must;

                foreach ($filters as $filterKey => $filterDetails) {
                    if ($filterKey === $key) continue;
                    if ($request->has($filterKey) && is_array($request->input($filterKey))) {
                        $localMust[] = ['terms' => [$filterDetails['column'] => $request->input($filterKey)]];
                    }
                }

                // Apply search on selected attribute
                if (
                    $searchAttribute === $key &&
                    $searchValue &&
                    in_array($searchAttribute, $validListings)
                ) {
                    // Get matching IDs from name search
                    $nameMatches = DB::table($config['table'])
                        ->where('name', 'LIKE', "%{$searchValue}%")
                        ->pluck('id')
                        ->toArray();

                    $localMust[] = ['terms' => [$config['column'] => $nameMatches]];
                }

                $params = [
                    'index' => $index,
                    'body' => [
                        'size' => 0,
                        'query' => ['bool' => ['must' => $localMust]],
                        'aggs' => [
                            $key => [
                                'terms' => [
                                    'field' => $config['column'],
                                    'size' => 1000
                                ]
                            ]
                        ]
                    ]
                ];

                $results = $client->search($params);
                $buckets = $results['aggregations'][$key]['buckets'] ?? [];

                $names = DB::table($config['table'])->pluck('name', 'id');

                $response[$key] = collect($buckets)->map(function ($bucket) use ($names) {
                    return [
                        'id' => $bucket['key'],
                        'name' => $names[$bucket['key']] ?? 'unknown',
                        'count' => $bucket['doc_count'],
                    ];
                })->sortBy('name')->values();
            }

            if ($activeFilterKey) {
                return sendResponse(true, 200, ucfirst(str_replace('_', ' ', $activeFilterKey)) . ' Fetched Successfully!', [
                    $activeFilterKey => $response[$activeFilterKey] ?? [],
                ], 200);
            }

            return sendResponse(true, 200, 'Attributes Fetched Successfully!', $response, 200);
        } catch (\Exception $ex) {
            return sendResponse(false, 500, 'Internal Server Error', $ex->getMessage(), 500);
        }
    }


    /**
     * Filter Attributes and Manage Counts API.
     */
    public function OldFilterAttributes(Request $request)
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
                'manufacturers',
                'vehicle_models',
                'detailed_titles',
                'vehicle_types',
                'conditions',
                'fuels',
                'seller_types',
                'drive_wheels',
                'transmissions',
                'damages',
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
                        (int) str_replace(',', '', $request->input('odometer_max')),
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
                        } elseif ($auctionDateFrom && ! $auctionDateTo) {
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
                    $existingResults = $existingResults->select("{$details['column']} as id", DB::raw('COUNT(*) as count'))
                        ->groupBy("{$details['column']}");

                    // Apply pagination if parameters exist
                    if (! empty($perPage) && ! empty($page)) {
                        $existingResults = $existingResults->paginate($perPage, ['*'], 'page', $page);
                    } else {
                        $existingResults = $existingResults->get();
                    }

                    // Fetch related names in bulk
                    $relatedNames = DB::table($details['table'])
                        ->whereIn('id', $existingResults->pluck('id'))
                        ->pluck('name', 'id');

                    // Map results to the response structure
                    $response[$key] = $existingResults->map(function ($item) use ($relatedNames) {
                        return [
                            'id' => $item->id,
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
                    })->select("{$filters[$searchAttribute]['column']} as id", DB::raw('COUNT(*) as count'))
                        ->groupBy("{$filters[$searchAttribute]['column']}")
                        ->paginate($perPage, ['*'], 'page', $page);

                    // Fetch related names in bulk
                    $relatedNames = DB::table($filters[$searchAttribute]['table'])
                        ->whereIn('id', $filteredResults->pluck('id'))
                        ->pluck('name', 'id');

                    // Map results to the response structure
                    $response[$searchAttribute] = $filteredResults->map(function ($item) use ($relatedNames) {
                        return [
                            'id' => $item->id,
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
                if ($details['paginate'] && (! $activeFilterKey || $key === $activeFilterKey)) {
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
                $response[$key] = $results->map(function ($item) use ($relatedNames) {
                    return [
                        'id' => $item->id,
                        'name' => $relatedNames[$item->id] ?? 'unknown',
                        'count' => $item->count,
                    ];
                })->sortBy('name')->values();
            }

            // Return only the active filter's data if a specific filter is set
            if ($activeFilterKey) {
                return sendResponse(true, 200, ucfirst(str_replace('_', ' ', $activeFilterKey)) . ' Fetched Successfully!', [
                    $activeFilterKey => $response[$activeFilterKey],
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
            $vehicleRecords = VehicleRecord::selectRaw('
                COUNT(CASE WHEN sale_date IS NOT NULL THEN 1 END) as sale_records,
                COUNT(CASE WHEN sale_date IS NULL THEN 1 END) as no_sale_records,
                MAX(updated_at) as latest_update_time_utc
            ')
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
     * Get Vehicle Records Count Hourly/Minutes.
     */
    public function getRecordsByInterval(Request $request)
    {
        $interval = $request->input('interval', '10min'); // Default to 10min
        $startOfDay = Carbon::now()->startOfDay();
        $currentTime = Carbon::now();

        if ($interval === '10min') {
            $format = '%H:%i'; // MySQL format: 02:00, 02:10
        } elseif ($interval === 'hourly') {
            $format = '%H:00'; // MySQL format: 02:00, 03:00
        } else {
            return response()->json(['error' => 'Invalid interval'], 400);
        }

        // Optimized Query
        $records = VehicleRecord::query()
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as started, COUNT(*) as created")
            ->whereBetween('created_at', [$startOfDay, $currentTime])
            ->groupBy('started')
            ->orderBy('started', 'ASC')
            ->get();

        return response()->json($records);
    }

    /**
     * Send Quote API
     */
    public function sendQuote(Request $request)
    {
        try {
            $details = [
                'name' => $request->name,
                'phone_number' => $request->phone_number,
                'contact_platform' => $request->contact_platform,
                'url' => $request->url,
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
            $history = CronRunHistory::orderBy('id', 'desc')
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
    public function cacheKeyHistory(Request $request)
    {
        try {
            // Fetch the latest record(s) from the cron_run_history table
            // $history = CacheKey::orderBy('id', 'desc')->get();
            $perPage = request()->get('per_page', 10); // Default to 10 if 'per_page' is not provided
            $page = request()->get('page', 1); // Default to page 1

            $history = CacheKey::select(['id', 'cache_key', 'expires_at', 'status', 'created_at', 'updated_at']) // Excludes 'cache_value'
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

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
            'data' => $records,
        ]);
    }

    public function removeStaleCacheKeys()
    {
        // Fetch all cache keys with status 'progress'
        $progressCacheKeys = CacheKey::where('status', 'progress')->get();

        foreach ($progressCacheKeys as $cacheKey) {
            $key = $cacheKey->cache_key;

            // Check if the key exists in the cache
            if (! Cache::has($key)) {

                // Delete the key from the database
                CacheKey::where('cache_key', $key)->delete();
            }
        }

        return sendResponse(true, 200, 'Removing stale cache key successfully!', [], 200);
    }

    public function testApi(Request $request)
    {
        // Get the last cron job status
        $lastCron = DB::table('cron_run_history')
            ->where('cron_name', 'process_buy_now_data')
            ->where('status', 'success')
            ->latest('start_time')
            ->first();

        $minutes = 10;

        if ($lastCron && $lastCron->end_time) {
            // Convert end_time to Carbon instance
            $endTime = Carbon::parse($lastCron->end_time);

            // Get the difference in minutes (ensure it's a non-negative integer)
            $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));

            // Apply the new conditions
            if ($timeDifference > 10) {
                $minutes = $timeDifference + 10;
            } elseif ($timeDifference === 10) {
                $minutes = $timeDifference + 5;
            }
        }

        return $minutes;

        // Fetch and update cache keys in a single query
        $cacheKeys = CacheKey::where('cache_key', 'like', 'buy_now_data%')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->take(50)
            ->get();

        $cacheKeyIds = $cacheKeys->pluck('id');

        // Update status to 'progress' in a single query
        // CacheKey::whereIn('id', $cacheKeyIds)->update(['status' => 'progress']);

        foreach ($cacheKeys as $cacheKey) {

            $key = $cacheKey->cache_key;
            $data = Cache::get($key);

            // Bulk update vehicles instead of looping individually
            $lotIds = collect($data)->pluck('lot')->toArray();

            // Fetch vehicles in a single query
            $vehicles = VehicleRecord::whereIn('lot_id', $lotIds)->get()->keyBy('lot_id');

            foreach ($data as $car) {
                if (isset($vehicles[$car['lot']])) {
                    $vehicles[$car['lot']]->update(['buy_now' => $car['buy_now']['value']]);
                }
            }

            return 'yes';
        }

        // Get the last cron job record
        // $lastCron = DB::table('cron_run_history')
        //     ->where('cron_name', 'process_vehicle_data')
        //     ->where('status', 'success')
        //     ->latest('start_time')
        //     ->first();

        // $minutes = 20; // Default minutes value

        // if ($lastCron && $lastCron->end_time) {
        //     // Convert end_time to Carbon instance
        //     $endTime = Carbon::parse($lastCron->end_time);

        //     // Get the difference in minutes (ensure it's a non-negative integer)
        //     $timeDifference = (int) max(0, $endTime->diffInMinutes(now()));

        //     // Apply the new conditions
        //     if ($timeDifference > 20) {
        //         $minutes = $timeDifference + 10;
        //     } elseif ($timeDifference === 20) {
        //         $minutes = $timeDifference + 5;
        //     }
        // }

        // return $minutes;
        // $now = now();
        // $sale_date = "2025-02-05T15:00:00.000000Z";

        // if($now < $sale_date) {
        //     return "now < sale_date".now();
        // } else if($now > $sale_date) {
        //     return "now > sale_date".now();
        // }
        // $expiredRecords = VehicleRecord::where('sale_date', '>', now())->take(100)->get();

        // return $expiredRecords;

        $expiredRecords = VehicleRecord::whereRaw("DATE_FORMAT(STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ'), '%Y-%m-%d %H:%i') <= ?", [now()->format('Y-m-d H:i')])
            ->take(100)
            ->get();

        return $expiredRecords;
    }

    public function getUncompressData(Request $request)
    {
        $cacheKey = DB::connection('mysql')->table('cache_keys')
            ->where('cache_key', 'like', $request->type . '%')
            ->orderBy('created_at', 'asc')
            ->first(); // ✅ Use first() instead of get()

        if ($cacheKey) {
            return json_decode($cacheKey->cache_value, true); // ✅ Access property directly
        }

        return [];
    }
}
