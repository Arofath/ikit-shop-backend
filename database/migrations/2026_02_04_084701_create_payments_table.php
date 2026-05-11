<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->decimal('amount', 12, 2); // ចំនួនទឹកប្រាក់ដែលបានបង់
            $table->string('payment_method')->default('CASH_ON_DELIVERY'); // COD, ABA, ACLEDA

            // 🌟 ផ្ទុករូបភាព Screenshot ពេលគាត់វេរលុយរួច
            $table->string('payment_proof')->nullable();

            $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED', 'REFUNDED'])->default('PENDING');
            $table->string('transaction_reference')->nullable(); // លេខកូដប្រតិបត្តិការ (Txn Hash)
            $table->timestamp('paid_at')->nullable(); // ថ្ងៃម៉ោងដែលប្រាក់បានចូល

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
