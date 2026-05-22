<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // ធ្វើឱ្យកូឡោនទាំងនេះអាចទទេបាន (Nullable)
            $table->string('shipping_phone')->nullable()->change();
            $table->text('shipping_address')->nullable()->change();
            $table->string('city')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_phone')->nullable(false)->change();
            $table->text('shipping_address')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
        });
    }
};
