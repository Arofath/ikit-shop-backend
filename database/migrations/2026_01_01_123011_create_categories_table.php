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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // Category name
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->uuid('parent_id')->nullable(); // Parent category reference
            $table->boolean('is_active')->default(true); // Active status 
            $table->timestamps();

            // Optional foreign key for parent category
            //$table->foreign('parent_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
