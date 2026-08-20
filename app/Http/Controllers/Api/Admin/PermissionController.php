<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * ១. ទាញយកបញ្ជី Permission ទាំងអស់ (Group តាម Entity)
     * ងាយស្រួលសម្រាប់ Frontend យកទៅគូរជា Matrix តាមម៉ូឌុលនីមួយៗ
     */
    public function index()
    {
        // 🌟 Group by 'entity' ទៅតាម Database ថ្មីដែលយើងទើប Migrate
        $groupedPermissions = Permission::orderBy('entity')->orderBy('name')->get()->groupBy('entity');

        return response()->json([
            'success' => true,
            'data'    => $groupedPermissions
        ]);
    }

    /**
     * ២. បង្កើត Permission ថ្មី (Dynamic)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255|unique:permissions,name', // P_Code ត្រូវតែ Unique
            'entity'       => 'required|string|max:100', // ឧ. Product, User
            'display_name' => 'required|string|max:255', // ឧ. Create new product
            'description'  => 'nullable|string'
        ]);

        try {
            $permission = Permission::create([
                'name'         => $request->name, // ឧ. product.create
                'guard_name'   => 'web', // ឬ api
                'entity'       => $request->entity,
                'display_name' => $request->display_name,
                'description'  => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully.',
                'data'    => $permission
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ៣. កែប្រែ Permission
     */
    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name'         => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'entity'       => 'required|string|max:100',
            'display_name' => 'required|string|max:255',
            'description'  => 'nullable|string'
        ]);

        try {
            $permission->update([
                'name'         => $request->name,
                'entity'       => $request->entity,
                'display_name' => $request->display_name,
                'description'  => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully.',
                'data'    => $permission
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ៤. លុប Permission
     */
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        // ការពារមិនឱ្យលុប Permission បើមាន Role កំពុងប្រើវា
        if ($permission->roles()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete this permission because it is assigned to one or more roles.'
            ], 400);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully.'
        ]);
    }
}
