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
        Schema::create('vehicle_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_id')->nullable();
            $table->integer('year')->nullable();
            $table->string('title')->nullable();    
            $table->string('vin')->nullable();
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->unsignedBigInteger('vehicle_model_id')->nullable();
            $table->unsignedBigInteger('generation_id')->nullable();
            $table->unsignedBigInteger('body_type_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->unsignedBigInteger('engine_id')->nullable();
            $table->unsignedBigInteger('transmission_id')->nullable();
            $table->unsignedBigInteger('drive_wheel_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->integer('cylinders')->nullable();
            $table->unsignedBigInteger('salvage_id')->nullable();
            $table->unsignedBigInteger('lot_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('external_id')->nullable();
            $table->unsignedBigInteger('odometer_km')->nullable();
            $table->unsignedBigInteger('odometer_mi')->nullable();
            $table->string('odometer_status')->nullable();
            $table->unsignedBigInteger('estimate_repair_price')->nullable();
            $table->unsignedBigInteger('pre_accident_price')->nullable();
            $table->unsignedBigInteger('clean_wholesale_price')->nullable();
            $table->unsignedBigInteger('actual_cash_value')->nullable();
            $table->string('sale_date')->nullable();
            $table->string('sale_date_updated_at')->nullable();
            $table->unsignedBigInteger('bid')->nullable();
            $table->string('bid_updated_at')->nullable();
            $table->unsignedBigInteger('buy_now')->nullable();
            $table->string('buy_now_updated_at')->nullable();
            $table->unsignedBigInteger('final_bid')->nullable();
            $table->string('final_bid_updated_at')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('seller_type_id')->nullable();
            $table->unsignedBigInteger('title_id')->nullable();
            $table->unsignedBigInteger('detailed_title_id')->nullable();
            $table->unsignedBigInteger('damage_id')->nullable();
            $table->unsignedBigInteger('damage_main')->nullable();
            $table->unsignedBigInteger('damage_second')->nullable();
            $table->boolean('keys_available')->nullable();
            $table->string('airbags')->nullable();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->string('grade_iaai')->nullable();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('selling_branch')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('set null');
            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('set null');
            $table->foreign('generation_id')->references('id')->on('generations')->onDelete('set null');
            $table->foreign('body_type_id')->references('id')->on('body_types')->onDelete('set null');
            $table->foreign('color_id')->references('id')->on('colors')->onDelete('set null');
            $table->foreign('engine_id')->references('id')->on('engines')->onDelete('set null');
            $table->foreign('transmission_id')->references('id')->on('transmissions')->onDelete('set null');
            $table->foreign('drive_wheel_id')->references('id')->on('drive_wheels')->onDelete('set null');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('set null');
            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('set null');
            $table->foreign('status_id')->references('id')->on('statuses')->onDelete('set null');
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('set null');
            $table->foreign('seller_type_id')->references('id')->on('seller_types')->onDelete('set null');
            $table->foreign('title_id')->references('id')->on('titles')->onDelete('set null');
            $table->foreign('detailed_title_id')->references('id')->on('detailed_titles')->onDelete('set null');
            $table->foreign('damage_id')->references('id')->on('damages')->onDelete('set null');
            $table->foreign('damage_main')->references('id')->on('damages')->onDelete('set null');
            $table->foreign('damage_second')->references('id')->on('damages')->onDelete('set null');
            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('set null');
            $table->foreign('image_id')->references('id')->on('images')->onDelete('set null');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('state_id')->references('id')->on('states')->onDelete('set null');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            $table->foreign('selling_branch')->references('id')->on('selling_branches')->onDelete('set null');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_records');
    }
};