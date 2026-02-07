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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique(); // ឧទាហរណ៍៖ ORD-20260204-0001
            $table->uuid('user_id'); // អ្នកទិញ
            $table->uuid('address_id')->nullable(); // អាសយដ្ឋានដឹកជញ្ជូន

            // ផ្នែកហិរញ្ញវត្ថុ
            $table->decimal('subtotal', 12, 2); // តម្លៃសរុបមុនបញ្ចុះតម្លៃ
            $table->decimal('discount_total', 12, 2)->default(0); // ចំនួនទឹកប្រាក់ដែលបានបញ្ចុះ
            $table->decimal('grand_total', 12, 2); // តម្លៃដែលត្រូវបង់ពិតប្រាកដ

            // ស្ថានភាព
            $table->enum('status', ['PENDING', 'PROCESSING', 'SHIPPED', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->enum('payment_status', ['UNPAID', 'PAID'])->default('UNPAID');
            $table->string('payment_method')->default('CASH_ON_DELIVERY');

            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
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
