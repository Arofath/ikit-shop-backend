<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * បង្ហាញបញ្ជីអាសយដ្ឋានទាំងអស់របស់ User
     */
    public function index(Request $request)
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc') // យកអាសយដ្ឋាន Default មកលើគេ
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => AddressResource::collection($addresses)->response()->getData(true)
        ]);
    }

    /**
     * បន្ថែមអាសយដ្ឋានថ្មី
     */
    public function store(Request $request)
    {
        $request->validate([
            'receiver_name'    => 'required|string|max:255',
            'receiver_phone'   => 'required|string|max:20',
            'address_detail'   => 'required|string',
            'shipping_zone_id' => 'required|exists:shipping_zones,id', // ✅ Add this line
            'is_default'       => 'boolean'
        ]);

        $userId = $request->user()->id;

        // ប្រសិនបើនេះជាអាសយដ្ឋានដំបូងគេ ត្រូវកំណត់វាជា Default ស្វ័យប្រវត្តិ
        $addressCount = Address::where('user_id', $userId)->count();
        $isDefault = ($addressCount === 0) ? true : ($request->is_default ?? false);

        DB::beginTransaction();
        try {
            // បើកំណត់ជា Default ត្រូវដក Default ពីអាសយដ្ឋានចាស់ៗចេញសិន
            if ($isDefault) {
                Address::where('user_id', $userId)->update(['is_default' => false]);
            }

            $address = Address::create([
                'user_id'          => $userId,
                'receiver_name'    => $request->receiver_name,
                'receiver_phone'   => $request->receiver_phone,
                'address_detail'   => $request->address_detail,
                'shipping_zone_id' => $request->shipping_zone_id, // ✅ Make sure you are saving the zone ID, not the city
                'is_default'       => $isDefault,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Address added successfully.',
                'data'    => new AddressResource($address)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'receiver_name'    => 'required|string|max:255',
            'receiver_phone'   => 'required|string|max:20',
            'address_detail'   => 'required|string',
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
            'is_default'       => 'boolean'
        ]);

        $userId = $request->user()->id;
        $address = Address::where('id', $id)->where('user_id', $userId)->firstOrFail();

        DB::beginTransaction();
        try {
            $isDefault = $request->is_default ?? $address->is_default;

            // បើគាត់ចង់កំណត់វាជា Default ត្រូវដក Default ពីអាសយដ្ឋានផ្សេងសិន
            if ($isDefault && !$address->is_default) {
                Address::where('user_id', $userId)->update(['is_default' => false]);
            }

            $address->update([
                'receiver_name'    => $request->receiver_name,
                'receiver_phone'   => $request->receiver_phone,
                'address_detail'   => $request->address_detail,
                'shipping_zone_id' => $request->shipping_zone_id,
                'is_default'       => $isDefault,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully.',
                'data'    => new AddressResource($address)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function updateOrderAddress(Request $request, $id)
    {
        // ១. ស្វែងរកវិក្កយបត្ររបស់ User នោះ
        $order = Order::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        // ២. លក្ខខណ្ឌទី ១៖ បើអីវ៉ាន់ចេញដឹក ឬដល់ដៃភ្ញៀវហើយ គឺមិនអនុញ្ញាតឱ្យកែទេ
        if (in_array($order->status, ['SHIPPING', 'COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order is already processed or shipped. Cannot update address.'
            ], 400);
        }

        // ៣. ផ្ទៀងផ្ទាត់ទិន្នន័យដែលបញ្ជូនមក
        $validated = $request->validate([
            'shipping_name'    => 'required|string|max:255',
            'shipping_phone'   => 'required|string|max:20',
            'shipping_address' => 'required|string', // នេះគឺជា address_detail
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
        ]);

        // ៤. លក្ខខណ្ឌទី ២៖ បើបង់លុយរួច (PAID) ហាមដូរខេត្តដាច់ខាត!
        if ($order->payment_status === 'PAID' && $order->shipping_zone_id !== $validated['shipping_zone_id']) {
            return response()->json([
                'success' => false,
                'message' => 'This order is already PAID. You cannot change the shipping province/city. Please contact support.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // អាប់ដេតព័ត៌មានទូទៅ
            $order->shipping_name    = $validated['shipping_name'];
            $order->shipping_phone   = $validated['shipping_phone'];
            $order->shipping_address = $validated['shipping_address'];

            // ៥. លក្ខខណ្ឌទី ៣៖ បើ UNPAID ហើយគាត់ដូរខេត្ត យើងត្រូវគណនាលុយឡើងវិញ
            if ($order->payment_status !== 'PAID' && $order->shipping_zone_id !== $validated['shipping_zone_id']) {

                $newZone = DB::table('shipping_zones')->where('id', $validated['shipping_zone_id'])->first();
                $order->shipping_zone_id = $newZone->id;

                // រូបមន្តគណនាថ្លៃដឹកជញ្ជូន
                $baseCost = $newZone->base_cost;

                // ឆែកមើលលក្ខខណ្ឌ Free Shipping Threshold
                if (!is_null($newZone->free_shipping_threshold) && $order->subtotal >= $newZone->free_shipping_threshold) {
                    $baseCost = 0;
                }

                // អាប់ដេតតម្លៃថ្មីចូល Order
                $order->base_shipping_cost = $baseCost;
                $order->shipping_fee = $baseCost + $order->bulky_surcharge_total;

                // គណនា Grand Total ថ្មី = Subtotal + Shipping - Discount (បើមាន)
                $discount = $order->discount_amount ?? 0;
                $order->grand_total = $order->subtotal + $order->shipping_fee - $discount;
            }

            $order->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order shipping address updated successfully.',
                'data'    => clone $order // បោះទិន្នន័យថ្មីទៅឱ្យ Frontend
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * កំណត់អាសយដ្ឋានណាមួយជា Default (ចម្បង)
     */
    public function setAsDefault(Request $request, $id)
    {
        $userId = $request->user()->id;

        $address = Address::where('id', $id)->where('user_id', $userId)->firstOrFail();

        DB::transaction(function () use ($userId, $address) {
            // ដក Default ពីអាសយដ្ឋានផ្សេងទៀត
            Address::where('user_id', $userId)->update(['is_default' => false]);

            // កំណត់អាសយដ្ឋាននេះជា Default
            $address->update(['is_default' => true]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Default address updated.'
        ]);
    }

    /**
     * លុបអាសយដ្ឋាន
     */
    public function destroy(Request $request, $id)
    {
        $address = Address::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($address->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the default address. Please set another one as default first.'
            ], 400);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.'
        ]);
    }
}
