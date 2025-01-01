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
        Schema::create('domain_detailed_title', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->unsignedBigInteger('detailed_title_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['domain_id', 'detailed_title_id'], 'unique_domain_detailed_title');

            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->foreign('detailed_title_id')->references('id')->on('detailed_titles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_detailed_title');
    }
};