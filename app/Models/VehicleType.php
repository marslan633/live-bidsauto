<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $fillable = ['id', 'vehicle_type_api_id', 'name', 'count'];

    public function manufacturers()
    {
        return $this->belongsToMany(Manufacturer::class, 'vehicle_type_manufacturer')->withPivot('count' , 'domain_id');
    }

    public function vehicle_models()
    {
        return $this->belongsToMany(VehicleModel::class, 'vehicle_type_vehicle_model')->withPivot('count' , 'domain_id');
    }

    public function conditions()
    {
        return $this->belongsToMany(Condition::class, 'vehicle_type_condition')->withPivot('count' , 'domain_id');
    }

    public function fuels()
    {
        return $this->belongsToMany(Fuel::class, 'vehicle_type_fuel')->withPivot('count' , 'domain_id');
    }

    public function seller_types()
    {
        return $this->belongsToMany(SellerType::class, 'vehicle_type_seller_type')->withPivot('count' , 'domain_id');
    }

    public function drive_wheels()
    {
        return $this->belongsToMany(DriveWheel::class, 'vehicle_type_drive_wheel')->withPivot('count' , 'domain_id');
    }

    public function transmissions()
    {
        return $this->belongsToMany(Transmission::class, 'vehicle_type_transmission')->withPivot('count' , 'domain_id');
    }

    public function detailed_titles()
    {
        return $this->belongsToMany(DetailedTitle::class, 'vehicle_type_detailed_title')->withPivot('count' , 'domain_id');
    }

    public function damages()
    {
        return $this->belongsToMany(Damage::class, 'vehicle_type_damage')->withPivot('count' , 'domain_id');
    }

    public function domains()
    {
        return $this->belongsToMany(Domain::class, 'vehicle_type_domain')->withPivot('count' , 'domain_id');
    }

    public function years()
    {
        return $this->belongsToMany(Year::class, 'vehicle_type_year')->withPivot('count' , 'domain_id');
    }

    public function buyNows()
    {
        return $this->belongsToMany(BuyNow::class, 'vehicle_type_buy_now')->withPivot('count' , 'domain_id');
    }
}