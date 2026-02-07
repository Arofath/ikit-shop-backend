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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('product_id');
            $table->uuid('order_id')->nullable(); // កត់ត្រាថាទិញពី Order ណា (Verified Purchase)

            $table->integer('rating')->default(5); // ១ ដល់ ៥ ផ្កាយ
            $table->text('comment')->nullable();
            $table->boolean('is_visible')->default(true); // Admin អាចបិទមតិដែលមិនសមរម្យ
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
