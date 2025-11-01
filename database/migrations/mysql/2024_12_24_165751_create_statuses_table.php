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
            $table->id(); // auto-incrementing primary key
            $table->unsignedBigInteger('status_api_id')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // Set auto-increment to start from 0
        DB::statement('ALTER TABLE statuses AUTO_INCREMENT = 0;');

        // Insert the "unknown" default record
        DB::connection('mysql')->table('statuses')->insertOrIgnore([
            'id' => 0,
            'status_api_id' => 0,
            'name' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reset auto-increment to start from 1 after inserting the 0 record
        DB::statement('ALTER TABLE statuses AUTO_INCREMENT = 1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
