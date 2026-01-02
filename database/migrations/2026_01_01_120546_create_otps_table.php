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
            $table->uuid('id')->primary(); // secure UUID
            $table->uuid('user_id'); // Foreign key to users table
            $table->enum('contact_type', ['email', 'phone']);
            $table->string('contact_value', 255);
            $table->string('otp_hash', 255);
            $table->enum('purpose', ['register', 'login', 'password_reset', 'verify']);
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
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
