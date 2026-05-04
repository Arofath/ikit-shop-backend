<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // បន្ថែម sort_order ចូល Table brands
        Schema::table('brands', function (Blueprint $table) {
            // ដាក់ default(0) ដើម្បីកុំឱ្យ Error ជាមួយទិន្នន័យចាស់ៗ
            $table->integer('sort_order')->default(0)->after('is_active');

        });

        // បន្ថែម sort_order ចូល Table categories
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('is_active');
            $table->boolean('is_popular')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // លុបវាវិញនៅពេលយើងរត់ command: php artisan migrate:rollback
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
            $table->dropColumn('is_popular');
        });
    }
};
