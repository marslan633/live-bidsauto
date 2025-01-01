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
        Schema::create('domain_damage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->unsignedBigInteger('damage_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['domain_id', 'damage_id'], 'unique_domain_damage');

            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('damage_id')->references('id')->on('damages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_damage');
    }
};