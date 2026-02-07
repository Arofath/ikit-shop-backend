<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSeries;
use Illuminate\Http\Request;
use App\Http\Resources\ProductSeriesResource;
use Illuminate\Support\Facades\DB;

class ProductSeriesController extends Controller
{
    public function index()
    {
        $series = ProductSeries::orderBy('created_at', 'desc')->get();
        return $this->sendResponse(ProductSeriesResource::collection($series), 'Product series retrieved.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'brand_id'    => 'required|exists:brands,id', // បន្ថែមការឆែក Brand ID
            'name'        => 'required|string|max:255|unique:product_series,name',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $series = ProductSeries::create($request->all());

        return $this->sendResponse(new ProductSeriesResource($series), 'Series created successfully.', 201);
    }

    public function update(Request $request, string $id)
    {
        $series = ProductSeries::findOrFail($id);

        $request->validate([
            'brand_id'    => 'sometimes|exists:brands,id',
            'name'        => 'sometimes|string|max:255|unique:product_series,name,' . $id,
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $series->update($request->all());

        return $this->sendResponse(new ProductSeriesResource($series), 'Series updated successfully.');
    }

    // បន្ថែមមុខងារសម្រាប់ប្តូរ status យ៉ាងរហ័ស
    public function toggleStatus(string $id)
    {
        $series = ProductSeries::findOrFail($id);
        $series->update(['is_active' => !$series->is_active]);

        return $this->sendResponse(
            new ProductSeriesResource($series),
            'Series status updated to ' . ($series->is_active ? 'Active' : 'Inactive')
        );
    }

    public function destroy(string $id)
    {
        $series = ProductSeries::findOrFail($id);

        return DB::transaction(function () use ($series) {
            $series->delete();
            return $this->sendResponse([], 'Series deleted successfully.');
        });
    }
}
