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
        Schema::create('condition_drive_wheel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->unsignedBigInteger('drive_wheel_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['condition_id', 'drive_wheel_id'], 'unique_condition_drive_wheel');

            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('cascade');
            $table->foreign('drive_wheel_id')->references('id')->on('drive_wheels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('condition_drive_wheel');
    }
};