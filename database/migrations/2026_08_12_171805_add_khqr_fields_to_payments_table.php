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
        Schema::table('payments', function (Blueprint $table) {

            // Payment currency
            $table->string('currency', 3)
                ->default('USD')
                ->after('amount');

            // KHQR MD5
            $table->string('md5', 64)
                ->nullable()
                ->unique()
                ->after('payment_method');

            // Bakong transaction hash
            $table->string('transaction_hash')
                ->nullable()
                ->after('transaction_reference');

            // Sender Bakong account
            $table->string('from_account_id')
                ->nullable()
                ->after('transaction_hash');

            // Receiver Bakong account
            $table->string('to_account_id')
                ->nullable()
                ->after('from_account_id');

            // Bakong external reference
            $table->string('external_ref')
                ->nullable()
                ->after('to_account_id');

            // KHQR/payment expiration
            $table->timestamp('expires_at')
                ->nullable()
                ->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            $table->dropUnique([
                'payments_md5_unique',
            ]);

            $table->dropColumn([
                'currency',
                'md5',
                'transaction_hash',
                'from_account_id',
                'to_account_id',
                'external_ref',
                'expires_at',
            ]);
        });
    }
};
