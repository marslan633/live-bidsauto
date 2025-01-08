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
        // Manufacturer Buy Now
        Schema::create('manufacturer_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            
            $table->unique(['manufacturer_id', 'buy_now_id', 'domain_id'], 'uniq_manufacturer_buy_now_domain');
        });

        // Vehicle Model Buy Now
        Schema::create('vehicle_model_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_model_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['vehicle_model_id', 'buy_now_id', 'domain_id'], 'uniq_vehicle_model_buy_now_domain');
            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Vehicle Type Buy Now
        Schema::create('vehicle_type_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['vehicle_type_id', 'buy_now_id', 'domain_id'], 'uniq_vehicle_type_buy_now_domain');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Condition Buy Now
        Schema::create('condition_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['condition_id', 'buy_now_id', 'domain_id'], 'uniq_condition_buy_now_domain');
            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Fuel Buy Now
        Schema::create('fuel_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['fuel_id', 'buy_now_id', 'domain_id'], 'uniq_fuel_buy_now_domain');
            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Seller Type Buy Now
        Schema::create('seller_type_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_type_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['seller_type_id', 'buy_now_id', 'domain_id'], 'uniq_seller_type_buy_now_domain');
            $table->foreign('seller_type_id')->references('id')->on('seller_types')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Drive Wheel Buy Now
        Schema::create('drive_wheel_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('drive_wheel_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['drive_wheel_id', 'buy_now_id', 'domain_id'], 'uniq_drive_wheel_buy_now_domain');
            $table->foreign('drive_wheel_id')->references('id')->on('drive_wheels')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Transmission Buy Now
        Schema::create('transmission_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transmission_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['transmission_id', 'buy_now_id', 'domain_id'], 'uniq_transmission_buy_now_domain');
            $table->foreign('transmission_id')->references('id')->on('transmissions')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Detailed Title Buy Now
        Schema::create('detailed_title_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('detailed_title_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['detailed_title_id', 'buy_now_id', 'domain_id'], 'uniq_detailed_title_buy_now_domain');
            $table->foreign('detailed_title_id')->references('id')->on('detailed_titles')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Damage Buy Now
        Schema::create('damage_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('damage_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['damage_id', 'buy_now_id', 'domain_id'], 'uniq_damage_buy_now_domain');
            $table->foreign('damage_id')->references('id')->on('damages')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        // Domain Buy Now
        Schema::create('domain_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['domain_id', 'buy_now_id'], 'uniq_domain_buy_now');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
        });

        // Year Buy Now
        Schema::create('year_buy_now', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('year_id')->nullable();
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->unique(['year_id', 'buy_now_id', 'domain_id'], 'uniq_year_buy_now_domain');
            $table->foreign('year_id')->references('id')->on('years')->onDelete('cascade');
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturer_buy_now');
        Schema::dropIfExists('vehicle_model_buy_now');
        Schema::dropIfExists('vehicle_type_buy_now');
        Schema::dropIfExists('condition_buy_now');
        Schema::dropIfExists('fuel_buy_now');
        Schema::dropIfExists('seller_type_buy_now');
        Schema::dropIfExists('drive_wheel_buy_now');
        Schema::dropIfExists('transmission_buy_now');
        Schema::dropIfExists('detailed_title_buy_now');
        Schema::dropIfExists('damage_buy_now');
        Schema::dropIfExists('domain_buy_now');
        Schema::dropIfExists('year_buy_now');
    }
};