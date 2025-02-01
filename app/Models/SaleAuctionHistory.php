<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleAuctionHistory extends Model
{
    protected $fillable = ['vin', 'domain_id', 'sale_date', 'lot_id', 'bid', 'odometer_mi', 'status_id', 'seller_id'];
}