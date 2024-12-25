<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    protected $fillable = [
        'seller_api_id',
        'name',
        'logo',
        'is_insurance',
        'is_rental',
        'is_credit_company',
    ];
}