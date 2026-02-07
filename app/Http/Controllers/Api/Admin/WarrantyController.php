<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warranty;
use Illuminate\Http\Request;
use App\Http\Resources\WarrantyResource;
use Illuminate\Support\Facades\DB;

class WarrantyController extends Controller
{
    // ១. បង្ហាញបញ្ជី Warranty ទាំងអស់
    public function index(Request $request)
    {
        $query = Warranty::query();

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        if ($request->has('status')) {
            $query->where('is_active', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        $warranties = $query->latest()->get();

        return $this->sendResponse(
            WarrantyResource::collection($warranties),
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

        $warranty->update($data);

        return $this->sendResponse(
            new WarrantyResource($warranty),
            'Warranty updated successfully.'
        );
    }

    // ៥. លុប (Security: មិនឱ្យលុបបើមាន Product កំពុងប្រើ)
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
