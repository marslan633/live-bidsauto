<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VehicleApiData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicle_api_data';
    protected $fillable = ['cache_value', 'created_at', 'expires_at'];

}
