<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

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

        if (in_array($request->role, ['admin', 'super_admin']) && !$currentUser->isSuperAdmin()) {
            return $this->sendError('Unauthorized: Only Super Admin can create core admin accounts.', [], 403);
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;
        $validated['is_2fa_enabled'] = false;
        $validated['require_password_change'] = $request->boolean('require_password_change', true);

        $user = clone User::create($validated);
        $user->profile()->firstOrCreate([]);
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

        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
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

        if (!$currentUser->isSuperAdmin()) {
            return $this->sendError('Unauthorized: Only Super Admin can change user roles.', [], 403);
        }

        $user->update(['role' => $request->role]);
        $user->syncRoles([$request->role]);

        return $this->sendResponse(new UserResource($user->load('profile')), 'User role updated successfully.');
    }

    // Delete user
    public function destroy(Request $request, string $id)
    {
        $userToDelete = User::findOrFail($id);
        $currentUser = $request->user();

        if ($userToDelete->email === config('app.super_admin_email')) {
            return $this->sendError('Unauthorized: The primary system owner account cannot be deleted.', [], 403);
        }

        if (($userToDelete->role === 'admin' || $userToDelete->isSuperAdmin()) && !$currentUser->isSuperAdmin()) {
            return $this->sendError('Unauthorized: Only Super Admin can delete other admins.', [], 403);
        }

        if ($currentUser->id === $userToDelete->id) {
            return $this->sendError('You cannot delete your own account.', [], 403);
        }

        $userToDelete->delete();

        return $this->sendResponse([], 'User deleted successfully.');
    }
}
