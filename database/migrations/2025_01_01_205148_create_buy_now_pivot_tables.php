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
        Schema::create('buy_now_manufacturer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'manufacturer_id', 'domain_id'], 'unique_buy_now_manufacturer_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Vehicle Model pivot table
        Schema::create('buy_now_vehicle_model', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('vehicle_model_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'vehicle_model_id', 'domain_id'], 'unique_buy_now_vehicle_model_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Vehicle Type pivot table
        Schema::create('buy_now_vehicle_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'vehicle_type_id', 'domain_id'], 'unique_buy_now_vehicle_type_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Condition pivot table
        Schema::create('buy_now_condition', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'condition_id', 'domain_id'], 'unique_buy_now_condition_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Fuel pivot table
        Schema::create('buy_now_fuel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'fuel_id', 'domain_id'], 'unique_buy_now_fuel_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Seller Type pivot table
        Schema::create('buy_now_seller_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('seller_type_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'seller_type_id', 'domain_id'], 'unique_buy_now_seller_type_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('seller_type_id')->references('id')->on('seller_types')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Drive Wheel pivot table
        Schema::create('buy_now_drive_wheel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('drive_wheel_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'drive_wheel_id', 'domain_id'], 'unique_buy_now_drive_wheel_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('drive_wheel_id')->references('id')->on('drive_wheels')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Transmission pivot table
        Schema::create('buy_now_transmission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('transmission_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'transmission_id', 'domain_id'], 'unique_buy_now_transmission_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('transmission_id')->references('id')->on('transmissions')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Detailed Title pivot table
        Schema::create('buy_now_detailed_title', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('detailed_title_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'detailed_title_id', 'domain_id'], 'unique_buy_now_detailed_title_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('detailed_title_id')->references('id')->on('detailed_titles')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Damage pivot table
        Schema::create('buy_now_damage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('damage_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'damage_id', 'domain_id'], 'unique_buy_now_damage_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('damage_id')->references('id')->on('damages')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Domain pivot table
        Schema::create('buy_now_domain', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'domain_id'], 'unique_buy_now_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Year - Buy Now pivot table
        Schema::create('buy_now_year', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['buy_now_id', 'year_id', 'domain_id'], 'unique_buy_now_year_domain');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the pivot tables
        Schema::dropIfExists('buy_now_manufacturer');
        Schema::dropIfExists('buy_now_vehicle_model');
        Schema::dropIfExists('buy_now_vehicle_type');
        Schema::dropIfExists('buy_now_condition');
        Schema::dropIfExists('buy_now_fuel');
        Schema::dropIfExists('buy_now_seller_type');
        Schema::dropIfExists('buy_now_drive_wheel');
        Schema::dropIfExists('buy_now_transmission');
        Schema::dropIfExists('buy_now_detailed_title');
        Schema::dropIfExists('buy_now_damage');
        Schema::dropIfExists('buy_now_domain');
        Schema::dropIfExists('buy_now_year');
    }
};