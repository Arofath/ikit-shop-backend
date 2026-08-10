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
        // យើងទាញយកឈ្មោះតារាងពី Config របស់ Spatie ដើម្បីការពារ Error ករណីបងធ្លាប់ដូរឈ្មោះតារាង
        $tableName = config('permission.table_names.permissions', 'permissions');

        Schema::table($tableName, function (Blueprint $table) {
            // 🌟 បន្ថែម Column group_name សម្រាប់បែងចែកក្រុមសិទ្ធិនៅលើផ្ទាំង UI
            $table->string('group_name')->nullable()->after('guard_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('permission.table_names.permissions', 'permissions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('group_name');
        });
    }
};
