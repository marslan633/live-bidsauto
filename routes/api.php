<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\CronRunHistoryController;
use App\Models\CacheKey;
use App\Models\RemoteCacheKey;
use App\Models\VehicleProcessCachedApiData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Console\Commands\ProcessCachedDataToDatabases;
use Carbon\Carbon;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('vehicles')->group(function () {
    Route::post('/', [VehicleController::class, 'vehicleInformations']);
    Route::get('/{id}', [VehicleController::class, 'searchVehicle']);
});

Route::post('filter-attributes', [VehicleController::class, 'filterAttributes']);
Route::post('vehicles-with-filter-attributes', [VehicleController::class, 'vehicleInformationsWithFilters']);
Route::get('filtered-records-count', [VehicleController::class, 'filteredRecordsCount'])->name('filtered.records.count');
Route::post('/sendQuote', [VehicleController::class, 'sendQuote']);
Route::get('cron-job-history', [VehicleController::class, 'cronJobHistory']);
Route::get('cache-key-history', [VehicleController::class, 'cacheKeyHistory']);
Route::get('get-max-record', [VehicleController::class, 'getMaxRecord']);
Route::get('test-api', [VehicleController::class, 'testApi']);
Route::get('removeStaleCacheKeys', [VehicleController::class, 'removeStaleCacheKeys']);
Route::get('/records-by-interval', [VehicleController::class, 'getRecordsByInterval']);

Route::get('get-read-redis-data', function(){
    $IS_KVM_TWO = config('app.is_kvm_two');
    $CacheModel = $IS_KVM_TWO ? RemoteCacheKey::class : CacheKey::class;
    $cacheKeys = $CacheModel::where('cache_key', 'like', 'vehicle_data%')
    ->where('status', 'pending')
    ->orderBy('created_at', 'asc')
    // ->lockForUpdate()
    // ->skipLocked()
    ->take(1)
    ->get();

    foreach ($cacheKeys as $cacheKey) {
        $key = $cacheKey->cache_key;
        $data = Cache::store('redis')->get($key);

        if (!$data) {
            CacheKey::where('cache_key', $key)->delete();
            return response()->json(['message' => 'Data not found']);
        }

        $processDataForCacheBefore = [];
        $processDataForCacheAfter = [];
        foreach ($data as $car) {
            $processDataForCacheBefore[] = $car;
            $processDataForCacheAfter[] = convertAndStoreDataToRedis($car);
        }

        return response()->json(['processDataForCacheBefore' => $processDataForCacheBefore[0], 'processDataForCacheAfter' => $processDataForCacheAfter[0]]);
    }
});

Route::get('store-redis-data-to-database', function(){
    $IS_KVM_TWO = config('app.is_kvm_two');
    $CacheModel =   RemoteCacheKey::class;
    $cacheKeys = $CacheModel::where('cache_key', 'like', 'vehicle_data%')
    ->where('status', 'pending')
    ->orderBy('created_at', 'asc')
    // ->lockForUpdate()
    // ->skipLocked()
    ->take(1)
    ->get();

    foreach ($cacheKeys as $cacheKey) {
        $key = $cacheKey->cache_key;
        $data = json_decode(Cache::store('redis')->get($key), true);

        if (!$data) {
            CacheKey::where('cache_key', $key)->delete();
            return response()->json(['message' => 'Data not found']);
        }

        $originalData = [];
        $databaseReturedData = [];
        foreach ($data as $car) {
            $convertedData = convertAndStoreDataToRedis($car);
            $originalData[] =  $convertedData;
            $databaseReturedData = (new ProcessCachedDataToDatabases)->prepareCarData($convertedData);
        }

        return response()->json(['originalData' => $originalData, 'databaseReturedData' => $databaseReturedData]);
    }
});

Route::get('get-jobs', function(Request $request){
    return DB::connection('mysql')->table($request->table)->paginate(100);
});

Route::get('get-redis-key', function(Request $request){
    return Cache::store($request->redis)->get($request->key);
});

Route::get('uncompressed-data',[VehicleController::class, 'getUncompressData']);

Route::get('/cron-run-histories', [CronRunHistoryController::class, 'index']);
Route::post('/cron-run-histories', [CronRunHistoryController::class, 'store']);
Route::put('/cron-run-histories/{id}', [CronRunHistoryController::class, 'update']);

// Route::delete('/vehicle-record/{id}', [VehicleController::class, 'destroy']);
Route::get('/get-vehicles-for-database', [VehicleController::class, 'getVechiclesForDatabase']);
Route::get('/get-archived-vehicles-for-database', [VehicleController::class, 'getArchivedVechiclesForDatabase']);
Route::post('/delete-my-vehicle/{id}', [VehicleController::class, 'deleteMyVehicle']);
Route::post('/delete-my-archive-vehicle/{id}', [VehicleController::class, 'deleteMyArchiveVehicle']);

Route::get('/get-uncompressed-data', function(){
    return unCompressData('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');
});

Route::get('/test-archived-dates', function(Request $request){
    // Retrieve the 'sale_date' parameter from the request, defaulting to null if not provided
    $saleDate = Carbon::createFromFormat('Y-m-d\TH:i:s.u\Z', $request->input('sale_date'));

    // Format both dates to 'Y-m-d H:i'
    $formattedSaleDate = $saleDate->format('Y-m-d H:i');
    $formattedNow = now()->format('Y-m-d H:i');

    // Query: match till minute
    $records = DB::table('vehicle_records')
        ->limit(10)
        ->whereRaw("DATE_FORMAT(STR_TO_DATE(sale_date, '%Y-%m-%dT%H:%i:%s.%fZ'), '%Y-%m-%d %H:%i') <= ?", [$formattedSaleDate])
        ->orderBy('created_at')
        ->get();

    // Compare till minute
    $comparionDates = $formattedSaleDate < $formattedNow ? 'Yes' : 'NO';

    return response()->json([
        'saleDate' => $formattedSaleDate,
        'now' => $formattedNow,
        'comparionDates' => $comparionDates,
        'records' => $records
    ]);

});
