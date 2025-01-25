<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CacheKey extends Model
{
    protected $fillable = [
        'cache_key',
        'cache_value',
        'expires_at',
        'status'
    ];
}