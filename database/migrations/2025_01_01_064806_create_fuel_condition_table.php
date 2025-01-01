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
        Schema::create('fuel_condition', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fuel_id')->nullable();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['fuel_id', 'condition_id'], 'unique_fuel_condition');

            $table->foreign('fuel_id')->references('id')->on('fuels')->onDelete('cascade');
            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_condition');
    }
};