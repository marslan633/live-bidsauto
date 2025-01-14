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
        Schema::create('cron_run_history', function (Blueprint $table) {
            $table->id();
            $table->string('cron_name')->nullable(); // Name of the cron job
            $table->timestamp('start_time')->nullable(); // When the cron started
            $table->timestamp('end_time')->nullable();   // When the cron ended
            $table->enum('status', ['running', 'success', 'failed']);
            $table->text('error_message')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_run_history');
    }
};