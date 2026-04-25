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
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // កែប្រែមកប្រើ Syntax ថ្មី
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');

            // បញ្ចូល Field ថ្មី (Snapshot ទិន្នន័យផលិតផល)
            $table->string('product_name')->nullable();
            $table->string('product_sku')->nullable();

            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2); // រក្សាតម្លៃលក់នៅពេលបញ្ជាទិញ
            $table->decimal('subtotal', 12, 2); // quantity * unit_price

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
