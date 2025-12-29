<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageRule extends Model
{
    protected $table = 'landing_page_rules'; // table name in the other DB
    protected $connection = 'landing';       // connection name defined in config/database.php

    protected $fillable = [
        'section_key',
        'section_title',
        'request_body',
        'limit',
        'is_active',
    ];

    protected $casts = [
        'request_body' => 'array',
        'is_active'    => 'boolean',
    ];
}