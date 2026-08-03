<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // កែប្រែ ENUM ដោយបន្ថែម admin_password_reset ចូល
        DB::statement("ALTER TABLE otps MODIFY purpose ENUM('register', 'login', 'password_reset', 'admin_password_reset') NOT NULL");

        // 💡 ចំណាំ៖ ប្រសិនបើពីមុនអ្នកមិនបានប្រើ ENUM ទេ តែប្រើ String សូមប្រើកូដនេះជំនួសវិញ៖
        // DB::statement("ALTER TABLE otps MODIFY purpose VARCHAR(50) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ត្រឡប់ទៅសភាពដើមវិញ (អត់មាន admin_password_reset)
        DB::statement("ALTER TABLE otps MODIFY purpose ENUM('register', 'login', 'password_reset') NOT NULL");
    }
};
