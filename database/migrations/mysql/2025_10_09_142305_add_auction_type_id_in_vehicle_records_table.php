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
            // Add the column first if it doesn't exist
            if (!Schema::hasColumn('vehicle_records', 'auction_type_id')) {
                $table->unsignedBigInteger('auction_type_id')->nullable()->after('id');
            }

            // Then add the foreign key
            $table->foreign('auction_type_id')
                ->references('id')
                ->on('auction_types')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_records', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_records', 'auction_type_id')) {
                $table->dropForeign(['auction_type_id']);
                $table->dropColumn('auction_type_id');
            }
        });
    }
};
