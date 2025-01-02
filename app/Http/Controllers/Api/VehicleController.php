<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{VehicleRecord, VehicleType, Domain};

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

            // Handling 'Domain'
            if ($request->has('domain_id')) {
                $query->where('domain_id', $request->input('domain_id'));
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
                'vehicle_models' => $domain->vehicleModels->map(function ($model) {
                    return [
                        'id' => $model->id,
                        'name' => $model->name,
                        'count' => $model->pivot->count ?? 0,
                    ];
                }),
                'vehicle_types' => $domain->vehicleTypes->map(function ($type) {
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
                'seller_types' => $domain->sellerTypes->map(function ($sellerType) {
                    return [
                        'id' => $sellerType->id,
                        'name' => $sellerType->name,
                        'count' => $sellerType->pivot->count ?? 0,
                    ];
                }),
                'drive_wheels' => $domain->driveWheels->map(function ($driveWheel) {
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
                'detailed_titles' => $domain->detailedTitles->map(function ($title) {
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
}