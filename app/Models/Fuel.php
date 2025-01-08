<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fuel extends Model
{
    protected $fillable = ['id', 'fuel_api_id', 'name', 'count'];

    public function manufacturers()
    {
        return $this->belongsToMany(Manufacturer::class, 'fuel_manufacturer')->withPivot('count' , 'domain_id');
    }

    public function vehicle_models()
    {
        return $this->belongsToMany(VehicleModel::class, 'fuel_vehicle_model')->withPivot('count' , 'domain_id');
    }

    public function vehicle_types()
    {
        return $this->belongsToMany(VehicleType::class, 'fuel_vehicle_type')->withPivot('count' , 'domain_id');
    }

    public function conditions()
    {
        return $this->belongsToMany(Condition::class, 'fuel_condition')->withPivot('count' , 'domain_id');
    }

    public function seller_types()
    {
        return $this->belongsToMany(SellerType::class, 'fuel_seller_type')->withPivot('count' , 'domain_id');
    }

    public function drive_wheels()
    {
        return $this->belongsToMany(DriveWheel::class, 'fuel_drive_wheel')->withPivot('count' , 'domain_id');
    }

    public function transmissions()
    {
        return $this->belongsToMany(Transmission::class, 'fuel_transmission')->withPivot('count' , 'domain_id');
    }

    public function detailed_titles()
    {
        return $this->belongsToMany(DetailedTitle::class, 'fuel_detailed_title')->withPivot('count' , 'domain_id');
    }

    public function damages()
    {
        return $this->belongsToMany(Damage::class, 'fuel_damage')->withPivot('count' , 'domain_id');
    }

    public function domains()
    {
        return $this->belongsToMany(Domain::class, 'fuel_domain')->withPivot('count' , 'domain_id');
    }

    public function years()
    {
        return $this->belongsToMany(Year::class, 'fuel_year')->withPivot('count' , 'domain_id');
    }

    public function buyNows()
    {
        return $this->belongsToMany(BuyNow::class, 'fuel_buy_now')->withPivot('count' , 'domain_id');
    }
}