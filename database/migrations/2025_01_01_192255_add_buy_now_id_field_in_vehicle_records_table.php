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
            $table->unsignedBigInteger('buy_now_id')->nullable();
            $table->foreign('buy_now_id')->references('id')->on('buy_nows')->onDelete('set null');
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