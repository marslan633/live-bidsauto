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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('location_api_id')->unique();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('name')->nullable();;
            $table->string('latitude')->nullable();;
            $table->string('longitude')->nullable();;
            $table->string('postal_code')->nullable();
            $table->boolean('is_offsite')->nullable();
            $table->text('raw')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};