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
        Schema::connection('mysql')->table('vehicle_record_archiveds', function (Blueprint $table) {
            $table->string('pre_accident_price')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('vehicle_record_archiveds', function (Blueprint $table) {
            $table->unsignedBigInteger('pre_accident_price')->nullable()->change();
        });
    }
};
