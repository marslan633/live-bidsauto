<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionType extends Model
{
    protected $fillable = ['id', 'auction_type_api_id', 'name'];
}