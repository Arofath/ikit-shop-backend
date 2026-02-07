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
            $table->uuid('user_id');
            $table->string('receiver_name');
            $table->string('receiver_phone');

            // ប្រើ Textarea ដូចក្នុងរូបភាពដែលអ្នកបានផ្ញើមក
            $table->text('address_detail'); // សម្រាប់សរសេរ៖ ភូមិ, សង្កាត់, ខណ្ឌ ឬចំណុចសម្គាល់

            $table->string('city'); // ភ្នំពេញ ឬ ខេត្តដទៃទៀត
            $table->string('postal_code')->nullable(); // ប្រហែលជាមិនសូវចាំបាច់នៅខ្មែរ តែអាចទុកសម្រាប់តំបន់ខ្លះ

            $table->boolean('is_default')->default(false); // សម្គាល់ថាជាអាសយដ្ឋានចម្បងសម្រាប់ដឹកជញ្ជូន
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
