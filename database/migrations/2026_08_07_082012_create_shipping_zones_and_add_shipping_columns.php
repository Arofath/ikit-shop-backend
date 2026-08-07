<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ១. បង្កើតតារាង Zone ដឹកជញ្ជូន (ប្រើ UUID)
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->uuid('id')->primary(); // 🌟 ប្តូរពី id() ទៅជា uuid()
            $table->string('name');
            $table->decimal('base_cost', 10, 2)->default(0.00);
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ២. បន្ថែមតម្លៃ Surcharge ទៅលើ Product
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('shipping_surcharge', 10, 2)->default(0.00)->after('price');
        });

        // ៣. បន្ថែម Column លើ Order 
        Schema::table('orders', function (Blueprint $table) {
            // 🌟 ត្រូវប្រាកដថា foreign key ក៏ជាប្រភេទ uuid ដែរទើបមិន Error
            $table->uuid('shipping_zone_id')->nullable()->after('shipping_address');
            $table->decimal('base_shipping_cost', 10, 2)->default(0.00)->after('shipping_zone_id');
            $table->decimal('bulky_surcharge_total', 10, 2)->default(0.00)->after('base_shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_zone_id', 'base_shipping_cost', 'bulky_surcharge_total']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('shipping_surcharge');
        });

        Schema::dropIfExists('shipping_zones');
    }
};
