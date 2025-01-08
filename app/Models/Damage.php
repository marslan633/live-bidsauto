<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Damage extends Model
{
    protected $fillable = ['id', 'damage_api_id', 'name', 'count'];

    public function manufacturers()
    {
        return $this->belongsToMany(Manufacturer::class, 'damage_manufacturer')->withPivot('count' , 'domain_id');
    }

    public function vehicle_models()
    {
        return $this->belongsToMany(VehicleModel::class, 'damage_vehicle_model')->withPivot('count' , 'domain_id');
    }

    public function vehicle_types()
    {
        return $this->belongsToMany(VehicleType::class, 'damage_vehicle_type')->withPivot('count' , 'domain_id');
    }

    public function conditions()
    {
        return $this->belongsToMany(Condition::class, 'damage_condition')->withPivot('count' , 'domain_id');
    }

    public function fuels()
    {
        return $this->belongsToMany(Fuel::class, 'damage_fuel')->withPivot('count' , 'domain_id');
    }

    public function seller_types()
    {
        return $this->belongsToMany(SellerType::class, 'damage_seller_type')->withPivot('count' , 'domain_id');
    }

    public function drive_wheels()
    {
        return $this->belongsToMany(DriveWheel::class, 'damage_drive_wheel')->withPivot('count' , 'domain_id');
    }

    public function transmissions()
    {
        return $this->belongsToMany(Transmission::class, 'damage_transmission')->withPivot('count' , 'domain_id');
    }

    public function detailed_titles()
    {
        return $this->belongsToMany(DetailedTitle::class, 'damage_detailed_title')->withPivot('count' , 'domain_id');
    }

    public function domains()
    {
        return $this->belongsToMany(Domain::class, 'damage_domain')->withPivot('count' , 'domain_id');
    }

    public function years()
    {
        return $this->belongsToMany(Year::class, 'damage_year')->withPivot('count' , 'domain_id');
    }

    public function buyNows()
    {
        return $this->belongsToMany(BuyNow::class, 'damage_buy_now')->withPivot('count' , 'domain_id');
    }
}