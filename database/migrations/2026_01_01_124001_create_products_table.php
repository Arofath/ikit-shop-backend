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
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->string('sku')->unique(); // Stock Keeping Unit. e.g., SKU-123

            $table->uuid('category_id'); // Foreign key to categories table
            $table->uuid('brand_id'); // Foreign key to brands table
            $table->uuid('warranty_id')->nullable(); // បន្ថែមចំណុចនេះ
            $table->uuid('product_series_id')->nullable(); // បន្ថែមចំណុចនេះ

            $table->text('description')->nullable(); // Product description
            $table->decimal('price', 12, 2); // Price (12 digits, 2 decimals)
            $table->decimal('discount_percent', 5, 2)->nullable(); // Optional discount percentage
            $table->boolean('is_active')->default(true); // Active status
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
            $table->foreign('warranty_id')->references('id')->on('warranties')->nullOnDelete();
            $table->foreign('product_series_id')->references('id')->on('product_series')->nullOnDelete();

            // Indexes
            $table->index(['category_id', 'is_active']);
            $table->index(['brand_id', 'is_active']);
            $table->index('product_series_id');
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
