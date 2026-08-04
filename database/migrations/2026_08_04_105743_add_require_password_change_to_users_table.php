<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ថែម Column ប្រភេទ boolean ថ្មីមួយ ដោយឱ្យតម្លៃដើមគឺ false
            $table->boolean('require_password_change')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('require_password_change');
        });
    }
};
