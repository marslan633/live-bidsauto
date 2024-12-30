<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Damage extends Model
{
    protected $fillable = ['id', 'damage_api_id', 'name', 'count'];
}