<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['location_api_id', 'city_id', 'name', 'latitude', 'longitude', 'postal_code', 'is_offsite', 'raw'];
}