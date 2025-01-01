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
        Schema::create('seller_type_vehicle_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_type_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['seller_type_id', 'vehicle_type_id'], 'unique_seller_type_vehicle_type');

            $table->foreign('seller_type_id')->references('id')->on('seller_types')->onDelete('cascade');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_type_vehicle_type');
    }
};