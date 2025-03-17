<?php

use App\Console\Commands\ProcessCachedDataToDatabases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\CronRunHistoryController;
use App\Models\CacheKey;
use App\Models\RemoteCacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('vehicles')->group(function () {
    Route::post('/', [VehicleController::class, 'vehicleInformations']);
    Route::get('/{id}', [VehicleController::class, 'searchVehicle']);
});

Route::prefix('vehicle/v1')->group(function () {
    Route::get('/fetch', [VehicleController::class, 'vehicleDataByMinutes']);
});


Route::post('filter-attributes', [VehicleController::class, 'filterAttributes']);
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

Route::post('/cron-run-histories', [CronRunHistoryController::class, 'store']);
Route::put('/cron-run-histories/{id}', [CronRunHistoryController::class, 'update']);
