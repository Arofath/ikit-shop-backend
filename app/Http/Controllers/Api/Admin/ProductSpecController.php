<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSpec;
use App\Http\Resources\ProductSpecResource; // សន្មតថាអ្នកមាន Resource នេះ
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSpecController extends Controller
{
    /**
     * Sync ព័ត៌មានបច្ចេកទេសទាំងអស់របស់ Product
     */
    public function sync(Request $request, Product $product)
    {
        // ប្រើប្រាស់ Array Validation យ៉ាងត្រឹមត្រូវ
        $request->validate([
            'specs'              => 'present|array', // present មានន័យថាត្រូវតែមាន key នេះ ទោះវាទទេក៏ដោយ []
            'specs.*.spec_group' => 'required|string|max:100',
            'specs.*.spec_key'   => 'required|string|max:100',
            'specs.*.spec_value' => 'required|string',
            'specs.*.sort_order' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($request, $product) {

            // ១. លុប Specs ចាស់ៗរបស់ផលិតផលនេះចោលទាំងអស់
            $product->specs()->delete();

            // ២. រៀបចំទិន្នន័យ និងបញ្ចូល Specs ថ្មី ក្នុងល្បឿនលឿន (Bulk Insert)
            if (!empty($request->specs)) {
                $specsData = [];

                // រៀបចំ Array មួយជួរម្តងៗ
                foreach ($request->specs as $index => $spec) {
                    $specsData[] = [
                        'id'         => (string) Str::uuid(), // 🌟 បង្កើត UUID ដោយខ្លួនឯង
                        'product_id' => $product->id, // 🌟 ភ្ជាប់ទៅកាន់ Product ID
                        'spec_group' => $spec['spec_group'],
                        'spec_key'   => $spec['spec_key'],
                        'spec_value' => $spec['spec_value'],
                        // បើអត់ដាក់ sort_order មកទេ យើងយកលេខរៀង (index) របស់ Array ផ្ទាល់តែម្តង
                        'sort_order' => $spec['sort_order'] ?? $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // 🌟 បញ្ចូលទិន្នន័យទាំងអស់ក្នុងពេលតែមួយ (១ Query គត់ ទោះមាន ១០០ Specs ក៏ដោយ)
                ProductSpec::insert($specsData);

                // ប៉ះ (Touch) Product ឱ្យដឹងថាវាត្រូវបាន Update (ព្រោះ insert មិន Trigger Model Event ទេ)
                $product->touch();
            }

            // ៣. ផ្ញើទិន្នន័យដែលទើបនឹង Sync រួចទៅឱ្យ Frontend វិញ
            $syncedSpecs = $product->specs()->orderBy('sort_order')->get();

            return $this->sendResponse(
                ProductSpecResource::collection($syncedSpecs),
                'Product specifications synced successfully.'
            );
        });
    }
}
