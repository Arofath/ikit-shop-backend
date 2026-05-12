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
            'receiver_name'   => 'required|string|max:255',
            'receiver_phone'  => 'required|string|max:20',
            'address_detail'  => 'required|string',
            'city'            => 'required|string',
            'is_default'      => 'boolean'
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
                'user_id'        => $userId,
                'receiver_name'  => $request->receiver_name,
                'receiver_phone' => $request->receiver_phone,
                'address_detail' => $request->address_detail,
                'city'           => $request->city,
                'is_default'     => $isDefault,
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
