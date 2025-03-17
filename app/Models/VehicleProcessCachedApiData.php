<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VehicleProcessCachedApiData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicle_process_cached_api_data';
    protected $fillable = ['cache_value', 'created_at', 'updated_at', 'expires_at'];
}
