<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleRecord extends Model
{
    protected $fillable = [
        'api_id', 'year', 'title', 'vin', 'manufacturer_id', 'vehicle_model_id', 'generation_id', 
        'body_type_id', 'color_id', 'engine_id', 'transmission_id', 'drive_wheel_id', 
        'vehicle_type_id', 'fuel_id', 'cylinders', 'salvage_id', 'lot_id', 'domain_id', 
        'external_id', 'odometer_km', 'odometer_mi', 'odometer_status', 'estimate_repair_price', 
        'pre_accident_price', 'clean_wholesale_price', 'actual_cash_value', 'sale_date', 
        'sale_date_updated_at', 'bid', 'bid_updated_at', 'buy_now', 'buy_now_updated_at', 
        'final_bid', 'final_bid_updated_at', 'status_id', 'seller_id', 'seller_type_id', 
        'title_id', 'detailed_title_id', 'damage_id', 'damage_main', 'damage_second', 'keys_available', 
        'airbags', 'condition_id', 'grade_iaai', 'image_id', 'country_id', 'state_id', 
        'city_id', 'location_id', 'selling_branch', 'details'
    ];

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function generation()
    {
        return $this->belongsTo(Generation::class);
    }

    public function bodyType()
    {
        return $this->belongsTo(BodyType::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function engine()
    {
        return $this->belongsTo(Engine::class);
    }

    public function transmission()
    {
        return $this->belongsTo(Transmission::class);
    }

    public function driveWheel()
    {
        return $this->belongsTo(DriveWheel::class);
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function fuel()
    {
        return $this->belongsTo(Fuel::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function sellerType()
    {
        return $this->belongsTo(SellerType::class);
    }

    public function title()
    {
        return $this->belongsTo(Title::class);
    }

    public function detailedTitle()
    {
        return $this->belongsTo(DetailedTitle::class);
    }

    public function damageMain()
    {
        return $this->belongsTo(Damage::class, 'damage_main');
    }

    public function damageSecond()
    {
        return $this->belongsTo(Damage::class, 'damage_second');
    }

    public function condition()
    {
        return $this->belongsTo(Condition::class);
    }

    public function image()
    {
        return $this->belongsTo(Image::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function sellingBranch()
    {
        return $this->belongsTo(SellingBranch::class);
    }
}