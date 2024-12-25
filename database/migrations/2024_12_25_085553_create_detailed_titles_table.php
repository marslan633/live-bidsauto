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
        Schema::create('detailed_titles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('detailed_title_api_id')->unique();
            $table->string('code')->nullable();
            $table->string('name')->nullable();;
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detailed_titles');
    }
};