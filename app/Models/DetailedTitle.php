<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailedTitle extends Model
{
    protected $fillable = [
        'detailed_title_api_id',
        'code',
        'name',
        'count'
    ];

    public function manufacturers()
    {
        return $this->belongsToMany(Manufacturer::class, 'detailed_title_manufacturer')->withPivot('count' , 'domain_id');
    }

    public function vehicle_models()
    {
        return $this->belongsToMany(VehicleModel::class, 'detailed_title_vehicle_model')->withPivot('count' , 'domain_id');
    }

    public function vehicle_types()
    {
        return $this->belongsToMany(VehicleType::class, 'detailed_title_vehicle_type')->withPivot('count' , 'domain_id');
    }

    public function conditions()
    {
        return $this->belongsToMany(Condition::class, 'detailed_title_condition')->withPivot('count' , 'domain_id');
    }

    public function fuels()
    {
        return $this->belongsToMany(Fuel::class, 'detailed_title_fuel')->withPivot('count' , 'domain_id');
    }

    public function seller_types()
    {
        return $this->belongsToMany(SellerType::class, 'detailed_title_seller_type')->withPivot('count' , 'domain_id');
    }

    public function drive_wheels()
    {
        return $this->belongsToMany(DriveWheel::class, 'detailed_title_drive_wheel')->withPivot('count' , 'domain_id');
    }

    public function transmissions()
    {
        return $this->belongsToMany(Transmission::class, 'detailed_title_transmission')->withPivot('count' , 'domain_id');
    }

    public function damages()
    {
        return $this->belongsToMany(Damage::class, 'detailed_title_damage')->withPivot('count' , 'domain_id');
    }

    public function domains()
    {
        return $this->belongsToMany(Domain::class, 'detailed_title_domain')->withPivot('count' , 'domain_id');
    }

    public function years()
    {
        return $this->belongsToMany(Year::class, 'detailed_title_year')->withPivot('count' , 'domain_id');
    }

    public function buyNows()
    {
        return $this->belongsToMany(BuyNow::class, 'detailed_title_buy_now')->withPivot('count' , 'domain_id');
    }
}