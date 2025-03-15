<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VehicleBuyNowApiData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicle_buy_now_api_data';
    protected $fillable = ['cache_value', 'created_at', 'updated_at', 'expires_at'];

    protected $casts = [
        'cache_value' => 'array',
    ];
}
