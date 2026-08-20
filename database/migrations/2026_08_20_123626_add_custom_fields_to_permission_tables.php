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
        $tableNames = config('permission.table_names');

        // ១. បន្ថែម Column ចូលតារាង permissions
        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->string('entity')->nullable()->after('guard_name');
            $table->string('display_name')->nullable()->after('entity');
            $table->text('description')->nullable()->after('display_name');
        });

        // ២. បន្ថែម Column ចូលតារាង roles
        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->text('description')->nullable()->after('guard_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        // លុប Column វិញនៅពេលយើងវាយ Command: php artisan migrate:rollback
        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->dropColumn(['entity', 'display_name', 'description']);
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
