<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailedTitle extends Model
{
    protected $fillable = [
        'detailed_title_api_id',
        'code',
        'name',
    ];
}