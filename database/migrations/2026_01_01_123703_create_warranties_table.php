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
        Schema::create('warranties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g. "1 Year Local Warranty" ឬ "3 Years Global"
            $table->unsignedInteger('duration_months'); // 24
            $table->enum('type', ['MANUFACTURER', 'STORE', 'LIMITED'])->default('STORE');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warranties');
    }
};
