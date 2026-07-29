<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ពង្រីកទំហំ Column ទៅជា VARCHAR(50)
        DB::statement("ALTER TABLE orders MODIFY payment_status VARCHAR(50) DEFAULT 'UNPAID'");
    }

    public function down(): void
    {
        // ពេល Rollback យើងប្តូរវាត្រឡប់មកទំហំចាស់វិញ (បើចាំបាច់)
        DB::statement("ALTER TABLE orders MODIFY payment_status VARCHAR(20) DEFAULT 'UNPAID'");
    }
};
