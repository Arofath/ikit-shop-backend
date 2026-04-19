<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;

class UserManagementController extends Controller
{
    // List users
    public function index(Request $request)
    {
        $currentUser = $request->user();

        // ដោះស្រាយ Bug កន្លែង Chain Query និង Return
        $users = User::with('profile')
            ->when(!$currentUser->isSuperAdmin(), function ($query) {
                // កុំប្រើ return ក្នុងនេះ! ប្រើ $query ផ្ទាល់
                $query->where('role', 'customer');
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->where('role', $request->role);
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                // ត្រូវប្រាកដថាប្រៀបធៀបជា boolean ឬ string '1'/'0'
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

        return response()->json([
            'success' => true,
            'message' => 'List of users fetched successfully.',
            'data' => UserResource::collection($users)->response()->getData(true),
        ], 200);
    }

    // បង្កើត User / Admin ថ្មី
    public function store(Request $request)
    {
        $currentUser = $request->user();

        // 🌟 ការពារមានតែ Super Admin ទេទើបអាចបង្កើត Admin ថ្មីបាន
        if ($request->role === 'admin' && !$currentUser->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Only Super Admin can create admin accounts.'], 403);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:admin,customer',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['is_active'] = true;

        $user = clone User::create($validated);

        // បង្កើត Profile ទទេមួយភ្ជាប់ទៅជាមួយ
        $user->profile()->create([]);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => new UserResource($user->load('profile'))
        ], 201);
    }

    // view user details
    public function show(string $id)
    {
        $user = User::with('profile')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'User details fetched successfully.',
            'data' => new UserResource($user)
        ], 200);
    }

    // Enable / Disable user
    public function updateStatus(Request $request, string $id)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // ១. ការពារខ្លួនឯង
        if ($currentUser->id === $user->id && !$request->is_active) {
            return response()->json(['success' => false, 'message' => 'You cannot disable your own account.'], 403);
        }

        // 🌟 ២. ការពារ Super Admin ពី Admin ធម្មតា
        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Cannot modify Super Admin status.'], 403);
        }

        $user->update(['is_active' => $request->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully.',
            'data' => new UserResource($user)
        ], 200);
    }

    // Change role
    public function updateRole(Request $request, string $id)
    {
        $request->validate([
            'role' => 'required|in:admin,customer',
        ]);

        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // ១. ការពារខ្លួនឯង
        if ($currentUser->id === $user->id) {
            return response()->json(['success' => false, 'message' => 'You cannot change your own role.'], 403);
        }

        // 🌟 ២. ការពារ Super Admin ពី Admin ធម្មតា និង ការពារមិនឱ្យ Admin ធម្មតាបង្កើត Admin ថ្មីផ្តេសផ្តាស
        if (!$currentUser->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Only Super Admin can change user roles.'], 403);
        }

        $user->update(['role' => $request->role]);

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully.',
            'data' => new UserResource($user->load('profile'))
        ], 200);
    }

    // Delete user
    public function destroy(Request $request, string $id)
    {
        $userToDelete = User::findOrFail($id);
        $currentUser = $request->user();

        // ១. ការពារ Super Admin និង Admin (កម្រិតសិទ្ធិដែលអ្នកបានធ្វើគឺត្រូវហើយ ខ្ញុំគ្រាន់តែពង្រឹងវា)
        if ($userToDelete->role === 'admin' && !$currentUser->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: Only Super Admin can delete admins.'], 403);
        }

        // ២. ការពារការលុបខ្លួនឯង
        if ($currentUser->id === $userToDelete->id) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 403); // ប្តូរទៅ 403 ឱ្យត្រូវស្តង់ដារ
        }

        $userToDelete->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.'
        ], 200);
    }
}
