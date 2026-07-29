<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // បន្ថែមវាល payment_note ជាប្រភេទ text ដើម្បីឱ្យ Admin អាចសរសេរអត្ថបទរាងវែងបាន
            $table->text('payment_note')->nullable()->after('payment_receipt');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_note');
        });
    }
};
