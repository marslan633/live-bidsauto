<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VehicleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('vehicles')->group(function () {
    Route::post('/', [VehicleController::class, 'vehicleInformations']);
    Route::get('/{id}', [VehicleController::class, 'searchVehicle']);
});
Route::post('filter-attributes', [VehicleController::class, 'filterAttributes']);
Route::get('filtered-records-count', [VehicleController::class, 'filteredRecordsCount'])->name('filtered.records.count');
Route::post('/sendQuote', [VehicleController::class, 'sendQuote']);
Route::get('cron-job-history', [VehicleController::class, 'cronJobHistory']);
Route::get('cache-key-history', [VehicleController::class, 'cacheKeyHistory']);
Route::get('get-max-record', [VehicleController::class, 'getMaxRecord']);
Route::get('test-api', [VehicleController::class, 'testApi']);
Route::get('removeStaleCacheKeys', [VehicleController::class, 'removeStaleCacheKeys']);