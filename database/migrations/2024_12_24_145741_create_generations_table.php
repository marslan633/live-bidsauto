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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('generation_api_id')->unique();
            $table->string('name')->nullable();
            $table->integer('cars_qty')->default(0);
            $table->integer('from_year')->nullable();
            $table->integer('to_year')->nullable();
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->unsignedBigInteger('model_id');
            $table->string('type')->nullable(); // cars or motorcycles
            $table->timestamps();

            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');
            $table->foreign('model_id')->references('id')->on('vehicle_models')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};