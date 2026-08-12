<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 🌟 ជំហានទី ១៖ ដូរពី foreignId ទៅ foreignUuid វិញ
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignUuid('shipping_zone_id') // <--- កែត្រង់នេះ
                ->nullable()
                ->after('address_detail')
                ->constrained('shipping_zones')
                ->nullOnDelete();
        });

        // ជំហានទី ២៖ ផ្ទេរទិន្នន័យ (Data Mapping)
        $zones = DB::table('shipping_zones')->get();
        foreach ($zones as $zone) {
            DB::table('addresses')
                ->where('city', $zone->name)
                ->update(['shipping_zone_id' => $zone->id]);
        }

        // ជំហានទី ៣៖ លុប Column city ចោល
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }

    public function down(): void
    {
        // ពេល Rollback ត្រូវបង្កើត Column city មកវិញ
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('city')->nullable()->after('address_detail');
        });

        // ផ្ទេរទិន្នន័យត្រឡប់ពី ID មកជាឈ្មោះ city វិញ
        $zones = DB::table('shipping_zones')->get();
        foreach ($zones as $zone) {
            DB::table('addresses')
                ->where('shipping_zone_id', $zone->id)
                ->update(['city' => $zone->name]);
        }

        // លុប Column shipping_zone_id ចោល
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['shipping_zone_id']);
            $table->dropColumn('shipping_zone_id');
        });
    }
};
