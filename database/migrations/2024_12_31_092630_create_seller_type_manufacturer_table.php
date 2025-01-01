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
        Schema::create('seller_type_manufacturer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_type_id')->nullable();
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['seller_type_id', 'manufacturer_id'], 'unique_seller_type_manufacturer');

            $table->foreign('seller_type_id')->references('id')->on('seller_types')->onDelete('cascade');
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_type_manufacturer');
    }
};