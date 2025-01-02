<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    protected $fillable = ['id', 'domain_api_id', 'name'];

    public function manufacturers()
    {
        return $this->belongsToMany(Manufacturer::class, 'domain_manufacturer')->withPivot('count');
    }

    public function vehicleModels()
    {
        return $this->belongsToMany(VehicleModel::class, 'domain_vehicle_model')->withPivot('count');
    }

    public function vehicleTypes()
    {
        return $this->belongsToMany(VehicleType::class, 'domain_vehicle_type')->withPivot('count');
    }

    public function conditions()
    {
        return $this->belongsToMany(Condition::class, 'domain_condition')->withPivot('count');
    }

    public function fuels()
    {
        return $this->belongsToMany(Fuel::class, 'domain_fuel')->withPivot('count');
    }

    public function sellerTypes()
    {
        return $this->belongsToMany(SellerType::class, 'domain_seller_type')->withPivot('count');
    }

    public function driveWheels()
    {
        return $this->belongsToMany(DriveWheel::class, 'domain_drive_wheel')->withPivot('count');
    }

    public function transmissions()
    {
        return $this->belongsToMany(Transmission::class, 'domain_transmission')->withPivot('count');
    }

    public function detailedTitles()
    {
        return $this->belongsToMany(DetailedTitle::class, 'domain_detailed_title')->withPivot('count');
    }

    public function damages()
    {
        return $this->belongsToMany(Damage::class, 'domain_damage')->withPivot('count');
    }

    public function years()
    {
        return $this->belongsToMany(Year::class, 'domain_year')->withPivot('count');
    }

    public function buyNows()
    {
        return $this->belongsToMany(BuyNow::class, 'domain_buy_now')->withPivot('count');
    }
}