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
        Schema::create('otps', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Flow ទី១ គឺត្រូវតែមាន User មុនទើបមាន OTP ដូច្នេះលែងត្រូវការ nullable() ទៀតហើយ
            $table->uuid('user_id');

            // រក្សាទុកដដែល ដើម្បីភាពងាយស្រួលថ្ងៃមុខ
            $table->enum('contact_type', ['email', 'phone'])->default('email');
            $table->string('contact_value', 255);
            $table->string('otp_hash', 255);
            $table->enum('purpose', ['register', 'login', 'password_reset', 'verify']);
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamps();

            // Indexes 
            $table->index(['contact_type', 'contact_value']);
            $table->index(['user_id', 'purpose']);
            $table->index('expires_at');

            // Foreign key constraint ប្រើ cascade ល្អបំផុតការពារកុំឱ្យសល់ទិន្នន័យសំរាម
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
