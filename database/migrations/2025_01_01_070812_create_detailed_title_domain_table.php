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
        Schema::create('detailed_title_domain', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('detailed_title_id')->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['detailed_title_id', 'domain_id'], 'unique_detailed_title_domain');

            $table->foreign('detailed_title_id')->references('id')->on('detailed_titles')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detailed_title_domain');
    }
};