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
        Schema::create('vehicle_model_detailed_title', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_model_id')->nullable();
            $table->unsignedBigInteger('detailed_title_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['vehicle_model_id', 'detailed_title_id'], 'unique_vehicle_model_detailed_title');

            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('cascade');
            $table->foreign('detailed_title_id')->references('id')->on('detailed_titles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_model_detailed_title');
    }
};