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
        Schema::create('landing_page_rules', function (Blueprint $table) {
            $table->id();
            $table->string('section_key')->unique();   // popular, luxury, atv...
            $table->string('section_title');
            $table->json('request_body');              // <-- THIS IS IMPORTANT
            $table->integer('limit')->default(8);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_rules');
    }
};