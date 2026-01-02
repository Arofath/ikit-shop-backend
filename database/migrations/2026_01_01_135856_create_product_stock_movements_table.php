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
        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id'); // FK to products.id
            $table->uuid('supplier_id')->nullable(); // FK to suppliers.id, nullable for OUT / ADJUST
            $table->enum('type', ['IN', 'OUT', 'ADJUST']);
            $table->integer('quantity');
            $table->decimal('cost_price', 12, 2)->nullable(); // only for IN
            $table->string('note')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
    }
};
