<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    protected $fillable = ['id', 'condition_api_id', 'name', 'count'];

    public function manufacturers()
    {
        return $this->belongsToMany(Manufacturer::class, 'condition_manufacturer')->withPivot('count' , 'domain_id');
    }

    public function vehicle_models()
    {
        return $this->belongsToMany(VehicleModel::class, 'condition_vehicle_model')->withPivot('count' , 'domain_id');
    }

    public function vehicle_types()
    {
        return $this->belongsToMany(VehicleType::class, 'condition_vehicle_type')->withPivot('count' , 'domain_id');
    }

    public function fuels()
    {
        return $this->belongsToMany(Fuel::class, 'condition_fuel')->withPivot('count' , 'domain_id');
    }

    public function seller_types()
    {
        return $this->belongsToMany(SellerType::class, 'condition_seller_type')->withPivot('count' , 'domain_id');
    }

    public function drive_wheels()
    {
        return $this->belongsToMany(DriveWheel::class, 'condition_drive_wheel')->withPivot('count' , 'domain_id');
    }

    public function transmissions()
    {
        return $this->belongsToMany(Transmission::class, 'condition_transmission')->withPivot('count' , 'domain_id');
    }

    public function detailed_titles()
    {
        return $this->belongsToMany(DetailedTitle::class, 'condition_detailed_title')->withPivot('count' , 'domain_id');
    }

    public function damages()
    {
        return $this->belongsToMany(Damage::class, 'condition_damage')->withPivot('count' , 'domain_id');
    }

    public function domains()
    {
        return $this->belongsToMany(Domain::class, 'condition_domain')->withPivot('count' , 'domain_id');
    }

    public function years()
    {
        return $this->belongsToMany(Year::class, 'condition_year')->withPivot('count' , 'domain_id');
    }

    public function buyNows()
    {
        return $this->belongsToMany(BuyNow::class, 'condition_buy_now')->withPivot('count' , 'domain_id');
    }
}