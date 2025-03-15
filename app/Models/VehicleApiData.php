<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class VehicleApiData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicle_api_data';
    protected $fillable = ['cache_value', 'expires_at'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = Carbon::now();
            $model->expires_at = Carbon::now()->addDays(intval(config('app.ttl_expiry'))); // Set TTL (7 days)
        });
    }

    protected $casts = [
        'cache_value' => 'array',
    ];


}
