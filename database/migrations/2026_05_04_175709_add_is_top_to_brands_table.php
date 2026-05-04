<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('brands', function (Blueprint $table) {
            // បន្ថែម is_top បន្ទាប់ពី is_active (ឬនៅទីតាំងដែលអ្នកចង់បាន)
            $table->boolean('is_top')->default(false)->after('is_active');
        });
    }

    public function down()
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('is_top');
        });
    }
};
