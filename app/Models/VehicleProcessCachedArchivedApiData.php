<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleProcessCachedArchivedApiData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicle_process_cached_archived_api_data';
    protected $fillable = ['cache_value', 'created_at', 'updated_at', 'expires_at'];

}
