<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warranty;
use App\Http\Resources\WarrantyResource; // សន្មតថាអ្នកមាន Resource នេះ
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    // ១. បង្ហាញបញ្ជី Warranty ទាំងអស់
    public function index(Request $request)
    {
        $query = Warranty::query();

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        if ($request->filled('status')) { // ប្រើ filled ជំនួស has ដើម្បីចៀសវាងតម្លៃទទេ
            // 🌟 ប្រើ boolean() របស់ Laravel
            $query->where('is_active', $request->boolean('status'));
        }

        // 🌟 ដូរពី get() មក paginate()
        $warranties = $query->latest()->paginate($request->get('per_page', 15));

        return $this->sendResponse(
            WarrantyResource::collection($warranties)->response()->getData(true),
            'Warranties retrieved successfully.'
        );
    }

    // ២. បង្កើត Warranty ថ្មី
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255|unique:warranties,name',
            'duration_months' => 'required|integer|min:0',
            'type'            => 'required|in:MANUFACTURER,STORE,LIMITED',
            'description'     => 'nullable|string',
            'is_active'       => 'boolean',
        ]);

        // កំណត់ is_active = true ជា Default បើអត់មានបញ្ជូនមក
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        $warranty = Warranty::create($data);

        return $this->sendResponse(
            new WarrantyResource($warranty),
            'Warranty created successfully.',
            201
        );
    }

    // ៣. បង្ហាញព័ត៌មានលម្អិត
    public function show(Warranty $warranty)
    {
        return $this->sendResponse(
            new WarrantyResource($warranty),
            'Warranty details retrieved.'
        );
    }

    // ៤. កែប្រែព័ត៌មាន
    public function update(Request $request, Warranty $warranty)
    {
        $data = $request->validate([
            'name'            => 'sometimes|required|string|max:255|unique:warranties,name,' . $warranty->id,
            'duration_months' => 'sometimes|required|integer|min:0',
            'type'            => 'sometimes|required|in:MANUFACTURER,STORE,LIMITED',
            'description'     => 'nullable|string',
            'is_active'       => 'boolean',
        ]);

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $warranty->update($data);

        return $this->sendResponse(
            new WarrantyResource($warranty),
            'Warranty updated successfully.'
        );
    }

    // ៥. លុប
    public function destroy(Warranty $warranty)
    {
        if ($warranty->products()->exists()) {
            return $this->sendError(
                'Action denied.',
                ['This warranty is being used by products and cannot be deleted.'],
                400
            );
        }

        $warranty->delete();

        return $this->sendResponse([], 'Warranty deleted successfully.');
    }
}
