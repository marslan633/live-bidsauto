<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CronRunHistory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'cron_run_histories';
    protected $fillable = ['cron_name', 'start_time', 'end_time', 'status', 'error_message'];
}
