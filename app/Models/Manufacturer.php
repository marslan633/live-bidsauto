<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    protected $fillable = ['id', 'manufacturer_api_id', 'name', 'cars_qty', 'image', 'models_qty', 'type'];
}