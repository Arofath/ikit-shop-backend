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
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();

            // ដក category_id ចេញ តាមបំណងរបស់អ្នក!
            $table->foreignUuid('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignUuid('warranty_id')->nullable()->constrained('warranties')->nullOnDelete();
            $table->foreignUuid('product_series_id')->nullable()->constrained('product_series')->nullOnDelete();

            $table->text('description')->nullable();

            // ផ្នែកហិរញ្ញវត្ថុ
            $table->decimal('price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();

            // ផ្នែកស្ថានភាពទំនិញ
            $table->boolean('is_active')->default(true);
            $table->boolean('is_serialized')->default(false);

            // Timestamp និង Soft Deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes (ដក index របស់ category_id ចេញ)
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
