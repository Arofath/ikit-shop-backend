<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    // ១. ទាញយកបញ្ជីតំបន់ដឹកជញ្ជូនទាំងអស់
    public function index(Request $request)
    {
        // ចំនួន Zone ដឹកជញ្ជូនជាទូទៅមិនមានច្រើនទេ (ឧ. ២៥ ខេត្តក្រុង) ដូច្នេះយើងអាចប្រើ get() តែម្តង
        $zones = ShippingZone::latest()->get();

        return $this->sendResponse($zones, 'Shipping zones fetched successfully.');
    }

    // ២. បង្កើតតំបន់ដឹកជញ្ជូនថ្មី
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:shipping_zones,name',
            'base_cost' => 'required|numeric|min:0',
            // អនុញ្ញាតឱ្យ Null ក្នុងករណីដែល Zone នោះអត់មានប្រូម៉ូសិនហ្វ្រីថ្លៃដឹក
            'free_shipping_threshold' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $zone = ShippingZone::create($validated);

        return $this->sendResponse($zone, 'Shipping zone created successfully.', 201);
    }

    // ៣. មើលព័ត៌មានលម្អិតរបស់ Zone នីមួយៗ
    public function show(string $id)
    {
        $zone = ShippingZone::findOrFail($id);

        return $this->sendResponse($zone, 'Shipping zone details fetched.');
    }

    // ៤. កែប្រែព័ត៌មាន (Update)
    public function update(Request $request, string $id)
    {
        $zone = ShippingZone::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:shipping_zones,name,' . $id,
            'base_cost' => 'sometimes|required|numeric|min:0',
            'free_shipping_threshold' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $zone->update($validated);

        return $this->sendResponse($zone, 'Shipping zone updated successfully.');
    }

    // ៥. បិទ ឬ បើក ដំណើរការ Zone នេះរហ័ស
    public function updateStatus(Request $request, string $id)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $zone = ShippingZone::findOrFail($id);
        $zone->update(['is_active' => $request->boolean('is_active')]);

        return $this->sendResponse($zone, 'Shipping zone status updated successfully.');
    }

    // ៦. លុប
    public function destroy(string $id)
    {
        $zone = ShippingZone::findOrFail($id);

        // ស្រេចចិត្ត៖ អ្នកអាចសរសេរកូដការពារមិនឱ្យលុប Zone ដែលកំពុងជាប់ Order នៅទីនេះបាន

        $zone->delete();

        return $this->sendResponse([], 'Shipping zone deleted successfully.');
    }
}
