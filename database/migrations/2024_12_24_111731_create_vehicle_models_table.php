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
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_model_api_id')->unique(); // Unique identifier from API
            $table->string('name')->nullable();
            $table->integer('cars_qty')->default(0);
            $table->unsignedBigInteger('manufacturer_id'); // Foreign key
            $table->integer('generations_qty')->default(0);
            $table->string('type')->nullable(); // To store cars or motorcycles
            $table->timestamps();

            // Add a foreign key constraint if the `manufacturers` table exists
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};