<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mysql')->create('cache_keys', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->longText('cache_value')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['progress', 'pending'])->default('pending')->nullable();
            $table->timestamps();
        });

        // DB::statement("ALTER TABLE cache_keys ADD cache_value LONGBLOB NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache_keys');
    }
};
