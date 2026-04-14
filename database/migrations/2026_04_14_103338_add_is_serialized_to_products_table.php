<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // បន្ថែម is_serialized ហើយកំណត់ Default ទៅ false (ព្រោះទំនិញភាគច្រើនអត់មាន Serial ទេ)
            $table->boolean('is_serialized')->default(false)->after('is_active');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_serialized');
        });
    }
};
