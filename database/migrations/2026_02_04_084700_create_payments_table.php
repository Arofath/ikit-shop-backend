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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->decimal('amount', 12, 2); // ចំនួនទឹកប្រាក់ដែលបានបង់
            $table->string('payment_method')->default('CASH_ON_DELIVERY'); // COD, ABA, KHQR
            $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED', 'REFUNDED'])->default('PENDING');
            $table->string('transaction_reference')->nullable(); // លេខកូដប្រតិបត្តិការ (ប្រសិនបើបង់តាមធនាគារ)
            $table->timestamp('paid_at')->nullable(); // ថ្ងៃខែឆ្នាំដែលបានបង់ប្រាក់
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
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
