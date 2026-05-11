<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique(); // ឧ. ORD-20260204-0001

            $table->foreignUuid('user_id')->constrained('users');
            $table->uuid('address_id')->nullable(); // គ្រាន់តែចំណាំថាគាត់រើសអាសយដ្ឋានមួយណា

            // 🌟 ថតចម្លង (Snapshot) ព័ត៌មានដឹកជញ្ជូន ការពារភ្ញៀវលុបអាសយដ្ឋានចាស់ចោល
            $table->string('shipping_name');
            $table->string('shipping_phone');
            $table->text('shipping_address');

            // ផ្នែកហិរញ្ញវត្ថុ
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);

            // ស្ថានភាព (Status)
            $table->enum('status', ['PENDING', 'PROCESSING', 'SHIPPED', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->enum('payment_status', ['UNPAID', 'PAID'])->default('UNPAID');
            $table->string('payment_method')->default('CASH_ON_DELIVERY');

            $table->text('note')->nullable(); // ចំណាំពីអតិថិជន
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
