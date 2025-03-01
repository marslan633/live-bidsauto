<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteCacheKey extends Model
{
    protected $connection = 'mysql_remote'; // Uses 'mysql_remote' connection
    protected $table = 'cache_keys';

    protected $fillable = [
        'cache_key',
        'cache_value',
        'expires_at',
        'status'
    ];
}
