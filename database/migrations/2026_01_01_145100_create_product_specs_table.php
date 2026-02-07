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
        Schema::create('product_specs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('spec_group', 100); // Processor, Memory, Display, etc.
            $table->string('spec_key', 100);   // CPU, RAM, Storage, Screen Size, etc.
            $table->text('spec_value'); // Intel i7, 16
            $table->integer('sort_order')->default(0); // បន្ថែមសម្រាប់រៀបលំដាប់
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['product_id', 'spec_group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_specs');
    }
};

