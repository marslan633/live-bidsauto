<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('image_api_id')->unique();
            $table->json('small')->nullable(); 
            $table->json('normal')->nullable(); 
            $table->json('big')->nullable();
            $table->json('downloaded')->nullable(); 
            $table->text('exterior')->nullable();
            $table->text('interior')->nullable(); 
            $table->text('video')->nullable(); 
            $table->text('video_youtube_id')->nullable(); 
            $table->text('external_panorama_url')->nullable();
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};