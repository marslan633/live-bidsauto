<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Generation extends Model
{
    protected $fillable = ['id', 'generation_api_id', 'name', 'cars_qty', 'from_year', 'to_year', 'manufacturer_id', 'model_id', 'type'];
}