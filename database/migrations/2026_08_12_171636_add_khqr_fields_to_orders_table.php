<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // Currency used for the order
            $table->string('currency', 3)
                ->default('USD')
                ->after('grand_total');

            // Payment expiration time for KHQR
            $table->timestamp('payment_expires_at')
                ->nullable()
                ->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'payment_expires_at',
            ]);
        });
    }
};
