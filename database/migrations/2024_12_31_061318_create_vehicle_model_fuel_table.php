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
        Schema::create('vehicle_model_fuel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_model_id')->nullable();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['vehicle_model_id', 'fuel_id'], 'unique_vehicle_model_fuel');

            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('cascade');
            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_model_fuel');
    }
};