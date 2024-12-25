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
        Schema::create('selling_branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('selling_branch_api_id')->nullable();
            $table->string('name')->nullable(); // Branch name
            $table->string('link')->nullable(); // Branch link, nullable
            $table->string('number')->nullable(); // Branch number, nullable
            $table->string('branch_id')->nullable(); // Unique identifier for branch
            $table->string('domain_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selling_branches');
    }
};