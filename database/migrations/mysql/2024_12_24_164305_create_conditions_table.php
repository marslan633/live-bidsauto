<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mysql')->create('conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('condition_api_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        
        DB::connection('mysql')->table('conditions')->insertOrIgnore([
            'condition_api_id' => 100,
            'name' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conditions');
    }
};