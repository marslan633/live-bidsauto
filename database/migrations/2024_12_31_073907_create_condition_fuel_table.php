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
        Schema::create('condition_fuel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['condition_id', 'fuel_id'], 'unique_condition_fuel');

            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('cascade');
            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('condition_fuel');
    }
};