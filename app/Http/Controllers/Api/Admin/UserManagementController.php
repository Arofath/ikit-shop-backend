<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    // List users
    public function index(Request $request)
    {
        $currentUser = $request->user();

        $users = User::with('profile')
            ->when($request->filled('role'), function ($query) use ($request) {
                // 🌟 Spatie មាន scope ឈ្មោះ `role()` ស្រាប់ ដើម្បីទាញយក User ពីតារាងថ្មីដោយស្វ័យប្រវត្តិ
                $query->role($request->role);
            })
            ->when($request->filled('date_filter'), function ($query) use ($request) {
                $range = $request->date_filter;
                $now = \Carbon\Carbon::now();

                switch ($range) {
                    case 'today':
                        $query->whereDate('created_at', clone $now);
                        break;
                    case 'yesterday':
                        $query->whereDate('created_at', (clone $now)->subDay());
                        break;
                    case 'last_7_days':
                        $query->whereBetween('created_at', [(clone $now)->subDays(6)->startOfDay(), (clone $now)->endOfDay()]);
                        break;
                    case 'last_month':
                        $lastMonth = (clone $now)->subMonth();
                        $query->whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year);
                        break;
                    case 'this_year':
                        $query->whereYear('created_at', $now->year);
                        break;
                    case 'this_month':
                    default:
                        $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
                        break;
                }
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->get('per_page', 10));

        $paginatedData = UserResource::collection($users)->response()->getData(true);

        return $this->sendResponse($paginatedData, 'List of users fetched successfully.');
    }

    public function getKpis(Request $request)
    {
        // ១. រាប់ចំនួន User សរុប
        $totalUsers = User::count();

        // ២. ទាញយក Roles ទាំងអស់ ព្រមទាំងរាប់ចំនួន User ក្នុង Role នីមួយៗ (Spatie Built-in)
        $roles = Role::withCount('users')->get();

        $roleCounts = $roles->map(function ($role) {
            return [
                'name' => $role->name,
                'count' => $role->users_count
            ];
        });

        // ៣. រាប់ចំនួន User ដែល Active និង Inactive
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
                'by_role' => $roleCounts // បោះ Array នេះទៅឱ្យ Vue Loop
            ]
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'role'     => 'required|exists:roles,name',
            'password' => 'required|string|min:8',
            'require_password_change' => 'boolean',
        ]);

        // 🌟 ច្បាប់តឹងរ៉ឹង៖ Admin ធម្មតាមិនអាចបង្កើតគណនី Super Admin បានទេ
        if ($request->role === 'super_admin' && !$currentUser->hasRole('super_admin')) {
            return $this->sendError('Unauthorized: Only Super Admin can create new super admin accounts.', [], 403);
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;
        $validated['is_2fa_enabled'] = false;
        $validated['require_password_change'] = $request->boolean('require_password_change', true);

        $user = clone User::create($validated);
        $user->profile()->firstOrCreate([]);

        // ផ្តល់សិទ្ធិតាមរយៈ Spatie
        $user->assignRole($request->role);

        return $this->sendResponse(new UserResource($user->load('profile')), 'Account created successfully.', 201);
    }

    // view user details
    public function show(string $id)
    {
        $user = User::with('profile')->findOrFail($id);
        return $this->sendResponse(new UserResource($user), 'User details fetched successfully.');
    }

    // Enable / Disable user
    public function updateStatus(Request $request, string $id)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $user = User::findOrFail($id);
        $currentUser = $request->user();

        if ($currentUser->id === $user->id && !$request->is_active) {
            return $this->sendError('You cannot disable your own account.', [], 403);
        }

        // 🌟 ការពារមិនឱ្យ Admin ធម្មតា បិទគណនីរបស់ Super Admin
        if ($user->hasRole('super_admin') && !$currentUser->hasRole('super_admin')) {
            return $this->sendError('Unauthorized: Cannot modify Super Admin status.', [], 403);
        }

        $user->update(['is_active' => $request->is_active]);

        return $this->sendResponse(new UserResource($user), 'User status updated successfully.');
    }

    // Change role
    public function updateRole(Request $request, string $id)
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::findOrFail($id);
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return $this->sendError('You cannot change your own role.', [], 403);
        }

        // 🌟 ច្បាប់តឹងរ៉ឹង៖ 
        // ១. ហាម Admin ធម្មតា ដកសិទ្ធិពី Super Admin ចាស់
        // ២. ហាម Admin ធម្មតា ផ្តល់សិទ្ធិ Super Admin ទៅកាន់នរណាម្នាក់
        $isTargetSuperAdmin = $user->hasRole('super_admin');
        $isAssigningSuperAdmin = $request->role === 'super_admin';

        if (($isTargetSuperAdmin || $isAssigningSuperAdmin) && !$currentUser->hasRole('super_admin')) {
            return $this->sendError('Unauthorized: Only Super Admin can manage super admin privileges.', [], 403);
        }

        // រក្សាការ Update Column ចាស់ (បើចាំបាច់) និងប្រើប្រាស់ Spatie ឱ្យ Sync ថ្មី
        $user->update(['role' => $request->role]);
        $user->syncRoles([$request->role]);

        return $this->sendResponse(new UserResource($user->load('profile')), 'User role updated successfully.');
    }

    // Delete user
    public function destroy(Request $request, string $id)
    {
        $userToDelete = User::findOrFail($id);
        $currentUser = $request->user();

        // 🌟 ការពារ System Owner មិនឱ្យគេលុបបានទាល់តែសោះ
        if ($userToDelete->email === config('app.super_admin_email')) {
            return $this->sendError('Unauthorized: The primary system owner account cannot be deleted.', [], 403);
        }

        // 🌟 ប្រើប្រាស់ Spatie: Admin ធម្មតា មិនអាចលុប Admin ដូចគ្នា ឬ Super Admin បានទេ
        if (($userToDelete->hasRole('admin') || $userToDelete->hasRole('super_admin')) && !$currentUser->hasRole('super_admin')) {
            return $this->sendError('Unauthorized: Only Super Admin can delete admin or super admin accounts.', [], 403);
        }

        if ($currentUser->id === $userToDelete->id) {
            return $this->sendError('You cannot delete your own account.', [], 403);
        }

        $userToDelete->delete();

        return $this->sendResponse([], 'User deleted successfully.');
    }
}
