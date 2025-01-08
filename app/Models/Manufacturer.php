<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    protected $fillable = ['id', 'manufacturer_api_id', 'name', 'cars_qty', 'image', 'models_qty', 'type', 'count'];
    
    public function vehicle_models()
    {
        return $this->belongsToMany(VehicleModel::class, 'manufacturer_vehicle_model')->withPivot('count' , 'domain_id');
    }

    public function vehicle_types()
    {
        return $this->belongsToMany(VehicleType::class, 'manufacturer_vehicle_type')->withPivot('count' , 'domain_id');
    }

    public function conditions()
    {
        return $this->belongsToMany(Condition::class, 'manufacturer_condition')->withPivot('count' , 'domain_id');
    }

    public function fuels()
    {
        return $this->belongsToMany(Fuel::class, 'manufacturer_fuel')->withPivot('count' , 'domain_id');
    }

    public function seller_types()
    {
        return $this->belongsToMany(SellerType::class, 'manufacturer_seller_type')->withPivot('count' , 'domain_id');
    }

    public function drive_wheels()
    {
        return $this->belongsToMany(DriveWheel::class, 'manufacturer_drive_wheel')->withPivot('count' , 'domain_id');
    }

    public function transmissions()
    {
        return $this->belongsToMany(Transmission::class, 'manufacturer_transmission')->withPivot('count' , 'domain_id');
    }

    public function detailed_titles()
    {
        return $this->belongsToMany(DetailedTitle::class, 'manufacturer_detailed_title')->withPivot('count' , 'domain_id');
    }

    public function damages()
    {
        return $this->belongsToMany(Damage::class, 'manufacturer_damage')->withPivot('count' , 'domain_id');
    }

    public function domains()
    {
        return $this->belongsToMany(Domain::class, 'manufacturer_domain')->withPivot('count' , 'domain_id');
    }

    public function years()
    {
        return $this->belongsToMany(Year::class, 'manufacturer_year')->withPivot('count' , 'domain_id');
    }

    public function buyNows()
    {
        return $this->belongsToMany(BuyNow::class, 'manufacturer_buy_now')->withPivot('count' , 'domain_id');
    }
}