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
            $table->unsignedBigInteger('year_id')->after('year')->nullable();
            $table->foreign('year_id')->references('id')->on('engines')->onDelete('set null');
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