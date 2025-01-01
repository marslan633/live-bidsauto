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
        Schema::create('damage_condition', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('damage_id')->nullable();
            $table->unsignedBigInteger('condition_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['damage_id', 'condition_id'], 'unique_damage_condition');

            $table->foreign('damage_id')->references('id')->on('damages')->onDelete('cascade');
            $table->foreign('condition_id')->references('id')->on('conditions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_condition');
    }
};