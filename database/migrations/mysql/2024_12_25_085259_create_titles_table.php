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
        Schema::connection('mysql')->create('titles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('title_api_id')->unique();
            $table->string('code')->nullable();
            $table->string('name')->nullable();;
            $table->timestamps();
        });


        DB::connection('mysql')->table('titles')->insertOrIgnore([
            'title_api_id' => 0,
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
        Schema::dropIfExists('titles');
    }
};