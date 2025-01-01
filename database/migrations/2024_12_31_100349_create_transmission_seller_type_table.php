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
        Schema::create('transmission_seller_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transmission_id')->nullable();
            $table->unsignedBigInteger('seller_type_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['transmission_id', 'seller_type_id'], 'unique_transmission_seller_type');

            $table->foreign('transmission_id')->references('id')->on('transmissions')->onDelete('cascade');
            $table->foreign('seller_type_id')->references('id')->on('seller_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transmission_seller_type');
    }
};