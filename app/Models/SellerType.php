<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerType extends Model
{
    protected $fillable = [
        'seller_type_api_id',
        'name'
    ];
}