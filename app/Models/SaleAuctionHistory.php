<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleAuctionHistory extends Model
{
    protected $fillable = ['vin', 'domain_id', 'sale_date', 'lot_id', 'bid', 'odometer_mi', 'status_id', 'seller_id'];

    // Relationship with Domain (Assuming a Domain model exists)
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    // Relationship with Status (Assuming a Status model exists)
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    // Relationship with Seller (Assuming a Seller model exists)
    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
    
    /**
     * Define the inverse relationship with VehicleRecordArchived.
     */
    public function vehicleRecord()
    {
        return $this->belongsTo(VehicleRecordArchived::class, 'vin', 'vin');
    }
}