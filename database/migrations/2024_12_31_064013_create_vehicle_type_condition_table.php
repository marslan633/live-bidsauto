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
        Schema::create('vehicle_type_condition', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_type_id')->nullable();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['vehicle_type_id', 'condition_id'], 'unique_vehicle_type_condition');

            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->onDelete('cascade');
            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_type_condition');
    }
};