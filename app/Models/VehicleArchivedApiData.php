<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VehicleArchivedApiData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicle_archived_api_data';
    protected $fillable = ['cache_value', 'created_at', 'updated_at', 'expires_at'];

}
