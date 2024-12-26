<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = ['image_api_id', 'small', 'normal', 'big', 'download', 'exterior', 'interior', 'video', 'video_youtube_id', 'external_panorama_url'];
}