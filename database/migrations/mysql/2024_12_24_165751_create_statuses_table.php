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
            $table->unsignedBigInteger('id')->primary(); // no auto-increment
            $table->unsignedBigInteger('status_api_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // Insert the 'unknown' row with 0,0
        DB::connection('mysql')->table('statuses')->insert([
            'id' => 0,
            'status_api_id' => 0,
            'name' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Now convert id into auto_increment starting from 1
        DB::statement('ALTER TABLE statuses MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;');
        DB::statement('ALTER TABLE statuses AUTO_INCREMENT = 1;');
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
