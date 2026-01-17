<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSpec;
use Illuminate\Http\Request;

class ProductSpecController extends Controller
{
    // List specs (grouped)
    public function index(Product $product)
    {
        $specs = $product->specs()
            ->orderBy('spec_group')
            ->orderBy('spec_key')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $specs,
        ]);
    }

    // Store new spec
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'specs' => 'required|array',
            'specs.*.spec_group' => 'required|string|max:100',
            'specs.*.spec_key'   => 'required|string|max:100',
            'specs.*.spec_value' => 'required|string',
        ]);

        $createdSpecs = [];

        foreach ($request->specs as $spec) {
            $createdSpecs[] = ProductSpec::create([
                'product_id' => $product->id,
                'spec_group' => $spec['spec_group'],
                'spec_key'   => $spec['spec_key'],
                'spec_value' => $spec['spec_value'],
            ]);
        }

        return response()->json([
            'message' => 'Specifications added successfully',
            'data' => $createdSpecs
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductSpec $spec)
    {
        $request->validate([
            'spec_group' => 'sometimes|string|max:100',
            'spec_key'   => 'sometimes|string|max:100',
            'spec_value' => 'sometimes|string',
        ]);

        $spec->update($request->only([
            'spec_group',
            'spec_key',
            'spec_value',
        ]));

        return response()->json([
            'message' => 'Specification updated successfully',
            'data' => $spec->fresh(), // 🔥 IMPORTANT
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductSpec $spec)
    {
        $spec->delete();

        return response()->json([
            'message' => 'Specification deleted successfully',
        ]);
    }
}
