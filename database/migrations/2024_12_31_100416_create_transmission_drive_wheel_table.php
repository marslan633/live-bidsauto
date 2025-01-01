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
        Schema::create('transmission_drive_wheel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transmission_id')->nullable();
            $table->unsignedBigInteger('drive_wheel_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['transmission_id', 'drive_wheel_id'], 'unique_transmission_drive_wheel');

            $table->foreign('transmission_id')->references('id')->on('transmissions')->onDelete('cascade');
            $table->foreign('drive_wheel_id')->references('id')->on('drive_wheels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transmission_drive_wheel');
    }
};