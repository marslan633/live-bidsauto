<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageRule extends Model
{
    protected $table = 'landing_page_rules';

    protected $fillable = [
        'section_key',
        'section_title',
        'request_body',
        'limit',
        'is_active'
    ];

    protected $casts = [
        'request_body' => 'array',
    ];
}