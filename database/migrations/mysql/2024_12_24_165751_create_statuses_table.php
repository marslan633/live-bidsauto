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
        Schema::connection('mysql')->create('statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('status_api_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        
        DB::connection('mysql')->table('statuses')->insertOrIgnore([
            'status_api_id' => 0,
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
        Schema::dropIfExists('statuses');
    }
};