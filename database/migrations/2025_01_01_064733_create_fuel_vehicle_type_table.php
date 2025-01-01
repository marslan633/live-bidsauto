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
        Schema::create('fuel_vehicle_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['fuel_id', 'vehicle_type_id'], 'unique_fuel_vehicle_type');

            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_vehicle_type');
    }
};