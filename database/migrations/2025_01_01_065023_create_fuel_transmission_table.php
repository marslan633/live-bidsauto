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
        Schema::create('fuel_transmission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->unsignedBigInteger('transmission_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['fuel_id', 'transmission_id'], 'unique_fuel_transmission');

            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
            $table->foreign('transmission_id')->references('id')->on('transmissions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_transmission');
    }
};