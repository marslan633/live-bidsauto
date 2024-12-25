<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellingBranch extends Model
{
    protected $fillable = [
        'name',
        'link',
        'number',
        'branch_id',
        'domain_id',
    ];
}