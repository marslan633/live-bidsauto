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
        // Year - Manufacturer pivot table
        Schema::create('year_manufacturer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'manufacturer_id'], 'unique_year_manufacturer');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');
        });

        // Year - Vehicle Model pivot table
        Schema::create('year_vehicle_model', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('vehicle_model_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'vehicle_model_id'], 'unique_year_vehicle_model');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('cascade');
        });

        // Year - Vehicle Type pivot table
        Schema::create('year_vehicle_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'vehicle_type_id'], 'unique_year_vehicle_type');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('cascade');
        });

        // Year - Condition pivot table
        Schema::create('year_condition', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'condition_id'], 'unique_year_condition');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('cascade');
        });

        // Year - Fuel pivot table
        Schema::create('year_fuel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'fuel_id'], 'unique_year_fuel');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
        });

        // Year - Seller Type pivot table
        Schema::create('year_seller_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('seller_type_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'seller_type_id'], 'unique_year_seller_type');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('seller_type_id')->references('id')->on('seller_types')->onDelete('cascade');
        });

        // Year - Drive Wheel pivot table
        Schema::create('year_drive_wheel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('drive_wheel_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'drive_wheel_id'], 'unique_year_drive_wheel');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('drive_wheel_id')->references('id')->on('drive_wheels')->onDelete('cascade');
        });

        // Year - Transmission pivot table
        Schema::create('year_transmission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('transmission_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'transmission_id'], 'unique_year_transmission');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('transmission_id')->references('id')->on('transmissions')->onDelete('cascade');
        });

        // Year - Detailed Title pivot table
        Schema::create('year_detailed_title', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('detailed_title_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'detailed_title_id'], 'unique_year_detailed_title');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('detailed_title_id')->references('id')->on('detailed_titles')->onDelete('cascade');
        });

        // Year - Damage pivot table
        Schema::create('year_damage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('damage_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'damage_id'], 'unique_year_damage');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('damage_id')->references('id')->on('damages')->onDelete('cascade');
        });

        // Year - Domain pivot table
        Schema::create('year_domain', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'domain_id'], 'unique_year_domain');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dropping the tables if migration is rolled back
        Schema::dropIfExists('year_manufacturer');
        Schema::dropIfExists('year_vehicle_model');
        Schema::dropIfExists('year_vehicle_type');
        Schema::dropIfExists('year_condition');
        Schema::dropIfExists('year_fuel');
        Schema::dropIfExists('year_seller_type');
        Schema::dropIfExists('year_drive_wheel');
        Schema::dropIfExists('year_transmission');
        Schema::dropIfExists('year_detailed_title');
        Schema::dropIfExists('year_damage');
        Schema::dropIfExists('year_domain');
    }
};