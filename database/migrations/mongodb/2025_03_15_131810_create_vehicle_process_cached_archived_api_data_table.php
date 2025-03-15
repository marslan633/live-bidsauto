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
        Schema::connection('mongodb')->create('vehicle_process_cached_archived_api_data', function (Blueprint $collection) {
            $collection->index('cache_value'); // Index for cache_value field
            $collection->date('created_at')->nullable(); // Automatically set on insert
            $collection->date('updated_at')->nullable(); // Automatically set on insert
            $collection->date('expires_at')->nullable(); // Expiration field for TTL
        });

        // **Create TTL Index (Auto-delete records after 7 days)**
        Schema::connection('mongodb')->table('vehicle_process_cached_archived_api_data', function (Blueprint $collection) {
            // Define the TTL index with the correct syntax
            $collection->index(
                ['expires_at' => 1],
                null,
                null,
                ['expireAfterSeconds' => 604800] // 7 days TTL
            );
        });

        // **Index created_at for sorting**
        // **Index updated_at for sorting**
        Schema::connection('mongodb')->table('vehicle_process_cached_archived_api_data', function (Blueprint $collection) {
            $collection->index('created_at');
            $collection->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('vehicle_process_cached_archived_api_data');
    }
};
