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
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
            $table->string('address')->nullable(); // អាសយដ្ឋានបច្ចុប្បន្ន
            $table->string('position')->nullable(); // មុខតំណែង (ឧ. 'Sales Manager', 'IT Support')
            $table->text('bio')->nullable(); // ការពិពណ៌នាខ្លីៗ ឬជំនាញ
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // លុប Column ទាំង ៤ នេះចេញវិញនៅពេលដែលយើងធ្វើការ Rollback
            $table->dropColumn([
                'date_of_birth',
                'address',
                'position',
                'bio'
            ]);
        });
    }
};
