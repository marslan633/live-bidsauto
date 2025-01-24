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
Route::post('domain/attributes', [VehicleController::class, 'getRelatedAttributes']);
Route::post('filter-attributes', [VehicleController::class, 'filterAttributes']);
Route::get('/test-cron', [VehicleController::class, 'testCron']);
Route::get('filtered-records-count', [VehicleController::class, 'filteredRecordsCount'])->name('filtered.records.count');
Route::post('/sendQuote', [VehicleController::class, 'sendQuote']);
Route::get('cron-job-history', [VehicleController::class, 'cronJobHistory']);