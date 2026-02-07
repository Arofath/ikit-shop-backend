<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSpec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ProductSpecResource;

class ProductSpecController extends Controller
{
    /**
     * Sync ព័ត៌មានបច្ចេកទេសទាំងអស់របស់ Product
     */
    public function sync(Request $request, Product $product)
    {
        $request->validate([
            'specs'              => 'present|array',
            'specs.*.spec_group' => 'required|string|max:100',
            'specs.*.spec_key'   => 'required|string|max:100',
            'specs.*.spec_value' => 'required|string',
            'specs.*.sort_order' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($request, $product) {
            // ១. លុប Specs ចាស់ៗរបស់ផលិតផលនេះចោលទាំងអស់
            $product->specs()->delete();

            // ២. បញ្ចូល Specs ថ្មីដែលផ្ញើមកពី Frontend
            if (!empty($request->specs)) {
                $product->specs()->createMany($request->specs);
            }

            // ផ្ញើទិន្នន័យដែលទើបនឹង Sync រួចទៅឱ្យ Frontend វិញ
            return $this->sendResponse(
                ProductSpecResource::collection($product->specs()->orderBy('sort_order')->get()),
                'Product specifications synced successfully.'
            );
        });
    }
}
