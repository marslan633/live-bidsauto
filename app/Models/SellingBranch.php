<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellingBranch extends Model
{
    protected $fillable = [
        'selling_branch_api_id',
        'name',
        'link',
        'number',
        'branch_id',
        'domain_id',
    ];
}