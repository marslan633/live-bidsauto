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
Route::post('filter-attributes/{attribute}', [VehicleController::class, 'filterAttributes']);