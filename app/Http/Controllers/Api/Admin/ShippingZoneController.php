<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    /**
     * ១. ទាញយកបញ្ជីតំបន់ដឹកជញ្ជូនទាំងអស់
     */
    public function index(Request $request)
    {
        $query = ShippingZone::query();

        // មុខងារស្វែងរកតាមឈ្មោះ
        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        // មុខងារ Filter តាម Status (Active / Inactive)
        if ($request->has('is_active') && $request->is_active != '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $zones = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Shipping zones retrieved successfully.',
            'data'    => $zones
        ]);
    }

    /**
     * ២. បង្កើតតំបន់ដឹកជញ្ជូនថ្មី
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'                    => 'required|string|max:255|unique:shipping_zones,name',
            'base_cost'               => 'required|numeric|min:0',
            'free_shipping_threshold' => 'nullable|numeric|min:0',
            'is_active'               => 'boolean'
        ]);

        $zone = ShippingZone::create([
            'name'                    => $request->name,
            'base_cost'               => $request->base_cost,
            'free_shipping_threshold' => $request->free_shipping_threshold,
            'is_active'               => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping zone created successfully.',
            'data'    => $zone
        ], 201);
    }

    /**
     * ៣. មើលព័ត៌មានតំបន់មួយជាក់លាក់
     */
    public function show($id)
    {
        $zone = ShippingZone::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $zone
        ]);
    }

    /**
     * ៤. កែប្រែព័ត៌មានតំបន់ដឹកជញ្ជូន
     */
    public function update(Request $request, $id)
    {
        $zone = ShippingZone::findOrFail($id);

        $request->validate([
            'name'                    => 'required|string|max:255|unique:shipping_zones,name,' . $zone->id,
            'base_cost'               => 'required|numeric|min:0',
            'free_shipping_threshold' => 'nullable|numeric|min:0',
            'is_active'               => 'boolean'
        ]);

        $zone->update([
            'name'                    => $request->name,
            'base_cost'               => $request->base_cost,
            'free_shipping_threshold' => $request->free_shipping_threshold,
            'is_active'               => $request->has('is_active') ? $request->is_active : $zone->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping zone updated successfully.',
            'data'    => $zone
        ]);
    }

    /**
     * ៥. លុបតំបន់ដឹកជញ្ជូន
     */
    public function destroy($id)
    {
        $zone = ShippingZone::findOrFail($id);

        // គួរមានការការពារ៖ មិនឱ្យលុបបើមានអតិថិជនធ្លាប់ប្រើ Zone នេះទិញទំនិញរួចហើយ
        $hasOrders = \App\Models\Order::where('shipping_zone_id', $zone->id)->exists();
        if ($hasOrders) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete this zone because it is currently used in existing orders. Consider disabling it instead.'
            ], 400);
        }

        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shipping zone deleted successfully.'
        ]);
    }

    /**
     * ៦. បិទ/បើក ដំណើរការ (Toggle Active Status)
     */
    public function toggleStatus($id)
    {
        $zone = ShippingZone::findOrFail($id);
        $zone->is_active = !$zone->is_active;
        $zone->save();

        return response()->json([
            'success' => true,
            'message' => 'Shipping zone status updated successfully.',
            'data'    => $zone
        ]);
    }
}
