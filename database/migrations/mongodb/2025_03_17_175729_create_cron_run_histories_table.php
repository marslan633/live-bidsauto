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
        Schema::connection('mongodb')->create('cron_run_histories', function (Blueprint $collection) {
            $collection->index('cron_name'); // Index for faster lookups
            $collection->string('cron_name')->nullable(); // Name of the cron job
            $collection->timestamp('start_time')->nullable(); // When the cron started
            $collection->timestamp('end_time')->nullable();   // When the cron ended
            $collection->enum('status', ['running', 'success', 'failed']); // Job status
            $collection->text('error_message')->nullable();
            $collection->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('cron_run_histories');
    }
};
