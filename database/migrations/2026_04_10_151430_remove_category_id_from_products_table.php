<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // វិធីសាស្ត្រប្រើ Raw SQL សម្រាប់ TiDB ដើម្បីរំលងបញ្ហាឈ្មោះ Index ខុស
        try {
            // ព្យាយាមលុប Column ត្រង់ៗសិន បើវាជាប់ Index វានឹងលោតចូល catch
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        } catch (\Exception $e) {
            // បើមាន Error (ជាប់ Index) យើងនឹងបញ្ជាឲ្យ TiDB ទម្លាក់ Index ដែលជាប់នឹង category_id នោះ

            // ១. ស្វែងរកឈ្មោះ Index ពិតប្រាកដដែលជាប់នឹង category_id
            $indexNameResult = DB::select("
                SELECT index_name 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                  AND table_name = 'products' 
                  AND column_name = 'category_id'
            ");

            if (!empty($indexNameResult)) {
                $indexName = $indexNameResult[0]->index_name;

                // ២. លុប Index នោះចេញ
                DB::statement("ALTER TABLE products DROP INDEX {$indexName}");

                // ៣. លុប Column category_id ម្តងទៀត បន្ទាប់ពីអស់ Index
                Schema::table('products', function (Blueprint $table) {
                    $table->dropColumn('category_id');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('category_id')->nullable()->constrained('categories');
        });
    }
};
