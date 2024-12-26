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
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manufacturer_api_id')->unique()->nullable();
            $table->string('name')->nullable();
            $table->integer('cars_qty')->default(0);
            $table->string('image')->nullable();
            $table->integer('models_qty')->default(0);
            $table->string('type')->nullable(); // Add 'type' column to store cars or motorcycles
            $table->timestamps();

            // Add the composite unique constraint
            $table->unique(['manufacturer_api_id', 'type'], 'manufacturer_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturers');
    }
};