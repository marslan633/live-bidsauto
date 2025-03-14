<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The name of the connection to use.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicle_api_data_collection', function (Blueprint $collection) {
            // Unique index on 'cache_key'
            $collection->unique('cache_key');

            // Index on 'cache_value' for optimized queries
            $collection->index('cache_value');

            // Index on 'status'
            $collection->index('status');

            // TTL index on 'expires_at' for automatic deletion
            $collection->expire('expires_at', 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_api_data_collection');
    }
};
