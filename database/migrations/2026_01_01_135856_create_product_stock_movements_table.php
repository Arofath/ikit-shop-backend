<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('supplier_id')->nullable();

            // បន្ថែម reference_number សម្រាប់កត់ត្រាលេខវិក្កយបត្រ ឬលេខកូដប្រតិបត្តិការ
            $table->string('reference_number')->nullable()->index();

            $table->enum('type', ['IN', 'OUT', 'ADJUST']);

            // ប្រើ unsignedInteger ដើម្បីការពារការបញ្ចូលលេខអវិជ្ជមាន
            $table->unsignedInteger('quantity');

            $table->decimal('cost_price', 12, 2)->nullable();

            // បន្ថែម balance_after សម្រាប់ដឹងចំនួនស្តុកដែលនៅសល់ភ្លាមៗបន្ទាប់ពីប្រតិបត្តិការនេះ
            // វាជួយឱ្យការទាញរបាយការណ៍លឿន (Optimization)
            $table->integer('balance_after')->nullable();

            $table->string('note')->nullable();
            $table->timestamps();

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



// Schema::create('product_stock_movements', function (Blueprint $table) {
//     $table->uuid('id')->primary();
//     $table->uuid('product_id');
//     $table->uuid('supplier_id')->nullable();

//     $table->enum('type', ['IN', 'OUT', 'ADJUST']);
//     $table->integer('quantity');
//     $table->decimal('cost_price', 12, 2)->nullable();

//     $table->string('reference_type')->nullable();
//     $table->uuid('reference_id')->nullable();

//     $table->string('note')->nullable();
//     $table->timestamps();

//     $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
//     $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();

//     $table->index('product_id');
//     $table->index('type');
// });
