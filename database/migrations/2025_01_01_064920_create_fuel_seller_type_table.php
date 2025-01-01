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
        Schema::create('fuel_seller_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->unsignedBigInteger('seller_type_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['fuel_id', 'seller_type_id'], 'unique_fuel_seller_type');

            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
            $table->foreign('seller_type_id')->references('id')->on('seller_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_seller_type');
    }
};