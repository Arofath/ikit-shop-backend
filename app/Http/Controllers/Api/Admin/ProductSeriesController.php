<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSeries;
use App\Http\Resources\ProductSeriesResource; // សន្មតថាអ្នកមាន Resource នេះ
use Illuminate\Http\Request;

class ProductSeriesController extends Controller
{
    // 🌟 បន្ថែមការភ្ជាប់ (with brand) និង Pagination ជំនួសឱ្យការទាញមកទាំងអស់ (get)
    public function index(Request $request)
    {
        $series = ProductSeries::with('brand')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return $this->sendResponse(
            ProductSeriesResource::collection($series)->response()->getData(true),
            'Product series retrieved.'
        );
    }

    public function store(Request $request)
    {
        // 🌟 ប្រើប្រាស់ $validated ដើម្បីចាប់យកតែទិន្នន័យដែលស្របច្បាប់ប៉ុណ្ណោះ
        $validated = $request->validate([
            'brand_id'    => 'required|exists:brands,id',
            'name'        => 'required|string|max:255|unique:product_series,name',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        // កំណត់ Default បើគេអត់បញ្ជូនមក
        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        $series = ProductSeries::create($validated);

        return $this->sendResponse(new ProductSeriesResource($series), 'Series created successfully.', 201);
    }

    public function update(Request $request, string $id)
    {
        $series = ProductSeries::findOrFail($id);

        $validated = $request->validate([
            'brand_id'    => 'sometimes|exists:brands,id',
            'name'        => 'sometimes|string|max:255|unique:product_series,name,' . $id,
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $series->update($validated);

        return $this->sendResponse(new ProductSeriesResource($series->load('brand')), 'Series updated successfully.');
    }

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

        // 🌟 Security Check: កុំឱ្យលុបបើមាន Product ឬ Slideshow ជាប់ជាមួយ
        if ($series->products()->exists() || $series->slideshows()->exists()) {
            return $this->sendError(
                'Action Denied.',
                ['Cannot delete this series because it has associated products or slideshows.'],
                400
            );
        }

        $series->delete();

        return $this->sendResponse([], 'Series deleted successfully.');
    }
}
