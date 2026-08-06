<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 🌟 បន្ថែម Column ជាប្រភេទ UUID ដោយសារ User ID របស់អ្នកជា UUID
            $table->uuid('status_updated_by')->nullable()->after('status');
            $table->uuid('payment_processed_by')->nullable()->after('payment_status');

            // ប្រសិនបើអ្នកចង់ភ្ជាប់ Foreign Key (ជាជម្រើស)
            // $table->foreign('status_updated_by')->references('id')->on('users')->nullOnDelete();
            // $table->foreign('payment_processed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 🌟 លុប Column វិញនៅពេលរត់ php artisan migrate:rollback
            // $table->dropForeign(['status_updated_by']);
            // $table->dropForeign(['payment_processed_by']);
            $table->dropColumn(['status_updated_by', 'payment_processed_by']);
        });
    }
};
