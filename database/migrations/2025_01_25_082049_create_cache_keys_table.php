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
        Schema::create('cache_keys', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique(); // Unique cache key
            $table->longText('cache_value')->nullable(); // Serialized cache value
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['progress', 'pending'])->default('pending')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache_keys');
    }
};