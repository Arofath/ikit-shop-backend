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
        Schema::create('product_serials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            // ភ្ជាប់ទៅកាន់ stock_movement ពេលដែលវាត្រូវបាននាំចូល (IN)
            $table->uuid('initial_movement_id');
            // ភ្ជាប់ទៅកាន់ stock_movement ពេលដែលវាត្រូវបានលក់ចេញ (OUT) - បើនៅមានក្នុងស្តុកគឺ null
            $table->uuid('sold_movement_id')->nullable();

            $table->string('serial_number')->unique(); // លេខស៊េរីត្រូវតែមានតែមួយ (Unique)
            $table->enum('status', ['AVAILABLE', 'SOLD', 'DEFECTIVE', 'RETURNED'])->default('AVAILABLE');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('initial_movement_id')->references('id')->on('product_stock_movements')->cascadeOnDelete();
            $table->foreign('sold_movement_id')->references('id')->on('product_stock_movements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};
