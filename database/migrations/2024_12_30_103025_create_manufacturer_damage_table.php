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
        Schema::create('manufacturer_damage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manufacturer_id')->nulable();
            $table->unsignedBigInteger('damage_id')->nulable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');
            $table->foreign('damage_id')->references('id')->on('damages')->onDelete('cascade');
            // Shorten the unique constraint name
            $table->unique(['manufacturer_id', 'damage_id'], 'unique_manufacturer_damage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturer_damage');
    }
};