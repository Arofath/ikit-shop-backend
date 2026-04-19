<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // 🌟 column group សម្រាប់បែងចែក Tab (ឧ. general, social, shipping)
            $table->string('group')->default('general');
            // 🌟 column key សម្រាប់ចំណាំ (ឧ. store_name) ត្រូវតែ Unique
            $table->string('key')->unique();
            // 🌟 column value សម្រាប់ផ្ទុកទិន្នន័យ (ដាក់ nullable ព្រោះខ្លះអាចអត់មានទិន្នន័យ)
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
