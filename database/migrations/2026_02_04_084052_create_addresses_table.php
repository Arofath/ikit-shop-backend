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
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ប្រើ Syntax ថ្មី
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->text('address_detail'); // ភូមិ សង្កាត់ ឬចំណុចសម្គាល់
            $table->string('city'); // ភ្នំពេញ ឬខេត្តផ្សេងៗ
            $table->boolean('is_default')->default(false); // សម្គាល់អាសយដ្ឋានចម្បង

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
