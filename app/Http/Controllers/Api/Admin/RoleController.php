<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * ១. ទាញយកបញ្ជី Role ទាំងអស់ រួមជាមួយចំនួន User ក្នុង Role នីមួយៗ
     */
    /**
     * ១. ទាញយកបញ្ជី Role ទាំងអស់ រួមជាមួយចំនួន User ក្នុង Role នីមួយៗ
     */
    public function index()
    {
        // 🌟 ដោះស្រាយបញ្ហា "Class name must be a valid object or a string"
        // ដោយប្តូរពីការប្រើ withCount('users') មក Query រាប់ដោយផ្ទាល់ពី DB វិញ

        $roles = Role::orderBy('created_at', 'desc')->get()->map(function ($role) {
            // រាប់ចំនួន User ដែលកាន់ Role នេះចេញពីតារាង model_has_roles
            $role->users_count = DB::table(config('permission.table_names.model_has_roles'))
                ->where('role_id', $role->id)
                ->count();

            return $role;
        });

        return response()->json([
            'success' => true,
            'data'    => $roles
        ]);
    }

    /**
     * ២. ទាញយកសិទ្ធិ (Permissions) ទាំងអស់ ដោយដាក់ជាក្រុមសម្រាប់គូរ UI Matrix
     */
    public function getPermissions()
    {
        // ទាញយក Permission ទាំងអស់ ហើយ Group វាទៅតាម Column `group_name`
        $permissions = Permission::all()->groupBy('group_name');

        return response()->json([
            'success' => true,
            'data'    => $permissions
        ]);
    }

    /**
     * ៣. បង្កើត Role ថ្មី និងកំណត់សិទ្ធិ
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array', // ជា Array នៃ Permission IDs ឬ Names
            'permissions.*' => 'exists:permissions,name'
        ]);

        DB::beginTransaction();
        try {
            // បង្កើត Role ថ្មី (Guard API)
            $role = Role::create([
                'name'       => $request->name,
                'guard_name' => 'web' // ឬ 'api' អាស្រ័យលើការកំណត់ដើមរបស់អ្នក
            ]);

            // ចងសិទ្ធិ (Sync) ទៅឱ្យ Role
            if ($request->has('permissions') && count($request->permissions) > 0) {
                $role->syncPermissions($request->permissions);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully.',
                'data'    => $role->load('permissions')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ៤. មើលព័ត៌មាន Role មួយជាក់លាក់ (សម្រាប់ពេលចុច Edit)
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $role
        ]);
    }

    /**
     * ៥. កែប្រែ Role និងសិទ្ធិ
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // ការពារមិនឱ្យកែប្រែ Role ប្រព័ន្ធសំខាន់ៗ
        if (in_array($role->name, ['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Core system roles cannot be modified directly.'
            ], 403);
        }

        $request->validate([
            'name'        => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        DB::beginTransaction();
        try {
            $role->update(['name' => $request->name]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully.',
                'data'    => $role->load('permissions')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ៦. លុប Role
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // ការពារមិនឱ្យលុប Role សំខាន់ៗ
        if (in_array($role->name, ['super_admin', 'admin', 'customer', 'sale_staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Core system roles cannot be deleted.'
            ], 403);
        }

        // ការពារមិនឱ្យលុបបើមាន User កំពុងកាន់ Role នេះ
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete this role because there are users assigned to it.'
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.'
        ]);
    }
}
