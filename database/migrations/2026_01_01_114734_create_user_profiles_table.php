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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();

            // ប្រើ syntax ថ្មីរបស់ Laravel សម្រាប់ Foreign Key (UUID)
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('profile_image')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            $table->date('date_of_birth')->nullable();
            $table->string('address')->nullable(); // អាសយដ្ឋានបច្ចុប្បន្ន
            $table->string('position')->nullable(); // មុខតំណែង (ឧ. 'Sales Manager', 'IT Support')
            $table->text('bio')->nullable(); // ការពិពណ៌នាខ្លីៗ ឬជំនាញ

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
