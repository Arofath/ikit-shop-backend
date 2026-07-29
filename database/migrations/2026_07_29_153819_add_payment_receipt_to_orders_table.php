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
        Schema::table('orders', function (Blueprint $table) {
            // បន្ថែម Column payment_receipt ជាប្រភេទ String ហើយអាចទទេបាន (nullable)
            // ដាក់វានៅបន្ទាប់ពី payment_method ដើម្បីងាយស្រួលមើល
            $table->string('payment_receipt')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_receipt');
        });
    }
};
