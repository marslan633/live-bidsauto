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
        Schema::create('damage_vehicle_model', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('damage_id')->nullable();
            $table->unsignedBigInteger('vehicle_model_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['damage_id', 'vehicle_model_id'], 'unique_damage_vehicle_model');

            $table->foreign('damage_id')->references('id')->on('damages')->onDelete('cascade');
            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_vehicle_model');
    }
};