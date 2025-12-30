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
        Schema::table('vehicle_records', function (Blueprint $table) {
            // Explicitly name indexes so dropIndex can use the same names
            $table->index('sale_date', 'sale_date');
            $table->index('year', 'year');
            $table->index('vin', 'vin');
            $table->index('lot_id', 'lot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_records', function (Blueprint $table) {
            // Use the same names as above
            $table->dropIndex('sale_date');
            $table->dropIndex('year');
            $table->dropIndex('vin');
            $table->dropIndex('lot_id');
        });
    }
};
