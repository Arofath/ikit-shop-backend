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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // Product name
            $table->uuid('category_id'); // Foreign key to categories table
            $table->uuid('brand_id'); // Foreign key to brands table
            $table->text('description')->nullable(); // Product description
            $table->decimal('price', 12, 2); // Price (12 digits, 2 decimals)
            $table->decimal('discount_percent', 5, 2)->nullable(); // Optional discount percentage
            $table->boolean('is_active')->default(true); // Active status
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
