<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VechicleApiData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vechicle_api_data';
}
