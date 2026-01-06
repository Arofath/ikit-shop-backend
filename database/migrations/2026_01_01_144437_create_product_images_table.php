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
        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('image_path');
            $table->boolean('is_thumbnail')->default(false);
            $table->integer('sort_order')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            // Optional index for performance
            $table->index(['product_id', 'is_thumbnail']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};

// Schema::create('product_images', function (Blueprint $table) {
//     $table->uuid('id')->primary();
//     $table->uuid('product_id');

//     $table->string('image_path');
//     $table->boolean('is_thumbnail')->default(false);
//     $table->integer('sort_order')->nullable();

//     $table->timestamps();

//     $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
//     $table->index(['product_id', 'is_thumbnail']);
// });
