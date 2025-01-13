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
        Schema::table('vehicle_records', function (Blueprint $table) {
            $table->unsignedBigInteger('odometer_id')->after('odometer_mi')->nullable();
            $table->foreign('odometer_id')->references('id')->on('odometers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_records', function (Blueprint $table) {
            //
        });
    }
};