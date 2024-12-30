<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transmission extends Model
{
    protected $fillable = ['id', 'transmission_api_id', 'name', 'count'];
}