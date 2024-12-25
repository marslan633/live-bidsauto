<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleModel extends Model
{
    protected $fillable = ['id', 'vehicle_model_api_id', 'name', 'cars_qty', 'manufacturer_id', 'generations_qty', 'type'];
}