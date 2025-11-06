<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('statuses', function (Blueprint $table) {
            $table->id(); // AUTO_INCREMENT starts from 1
            $table->unsignedBigInteger('status_api_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // Manually insert the "unknown" row with ID 0
        DB::statement('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"'); // allow manual 0 insert
        DB::table('statuses')->insertOrIgnore([
            'id' => 0,
            'status_api_id' => 0,
            'name' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement('SET SQL_MODE=""'); // restore SQL mode
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
