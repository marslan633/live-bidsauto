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
        Schema::create('detailed_title_vehicle_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('detailed_title_id')->nullable();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['detailed_title_id', 'vehicle_type_id'], 'unique_detailed_title_vehicle_type');

            $table->foreign('detailed_title_id')->references('id')->on('detailed_titles')->onDelete('cascade');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detailed_title_vehicle_type');
    }
};