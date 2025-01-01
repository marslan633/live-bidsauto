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
        Schema::create('drive_wheel_transmission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('drive_wheel_id')->nullable();
            $table->unsignedBigInteger('transmission_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['drive_wheel_id', 'transmission_id'], 'unique_drive_wheel_transmission');

            $table->foreign('drive_wheel_id')->references('id')->on('drive_wheels')->onDelete('cascade');
            $table->foreign('transmission_id')->references('id')->on('transmissions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drive_wheel_transmission');
    }
};