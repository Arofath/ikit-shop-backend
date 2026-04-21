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

        $users = User::with('profile')
            ->when(!$currentUser->isSuperAdmin(), function ($query) {
                // Admin ធម្មតាមើលឃើញត្រឹម Customer ទេ
                $query->where('role', 'customer');
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->where('role', $request->role);
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

        // ទាញយកទិន្នន័យ Pagination ឱ្យត្រូវទម្រង់
        $paginatedData = UserResource::collection($users)->response()->getData(true);

        return $this->sendResponse($paginatedData, 'List of users fetched successfully.');
    }

    // បង្កើត User / Admin / Super Admin ថ្មី
    public function store(Request $request)
    {
        $currentUser = $request->user();

        // 🌟 កែប្រែ៖ ត្រូវបន្ថែម 'super_admin' ចូលក្នុង Validation
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:customer,admin,super_admin',
        ]);

        // 🌟 ការពារមានតែ Super Admin ទេទើបអាចបង្កើត Admin ឬ Super Admin ថ្មីបាន
        if (in_array($request->role, ['admin', 'super_admin']) && !$currentUser->isSuperAdmin()) {
            return $this->sendError('Unauthorized: Only Super Admin can create admin accounts.', [], 403);
        }

        $validated['password'] = bcrypt($validated['password']);
        $validated['is_active'] = true;

        $user = clone User::create($validated);

        // បង្កើត Profile ទទេមួយភ្ជាប់ទៅជាមួយ
        $user->profile()->firstOrCreate([]);

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

        // ១. ការពារខ្លួនឯង
        if ($currentUser->id === $user->id && !$request->is_active) {
            return $this->sendError('You cannot disable your own account.', [], 403);
        }

        // ២. ការពារ Super Admin ពី Admin ធម្មតា (អត់មានការផ្លាស់ប្តូរទេ ព្រោះ Function isSuperAdmin ល្អស្រាប់)
        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            return $this->sendError('Unauthorized: Cannot modify Super Admin status.', [], 403);
        }

        $user->update(['is_active' => $request->is_active]);

        return $this->sendResponse(new UserResource($user), 'User status updated successfully.');
    }

    // Change role
    public function updateRole(Request $request, string $id)
    {
        // 🌟 កែប្រែ៖ ត្រូវបន្ថែម 'super_admin' ចូលក្នុង Validation
        $request->validate([
            'role' => 'required|in:customer,admin,super_admin',
        ]);

        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // ១. ការពារខ្លួនឯង
        if ($currentUser->id === $user->id) {
            return $this->sendError('You cannot change your own role.', [], 403);
        }

        // ២. ការពារមិនឱ្យ Admin ធម្មតាធ្វើការផ្លាស់ប្តូរ Role
        if (!$currentUser->isSuperAdmin()) {
            return $this->sendError('Unauthorized: Only Super Admin can change user roles.', [], 403);
        }

        $user->update(['role' => $request->role]);

        return $this->sendResponse(new UserResource($user->load('profile')), 'User role updated successfully.');
    }

    // Delete user
    public function destroy(Request $request, string $id)
    {
        $userToDelete = User::findOrFail($id);
        $currentUser = $request->user();

        // 🌟 ថ្មី៖ ការពារគណនី Founder មិនឱ្យត្រូវគេលុបបានទាល់តែសោះ (ទោះជា Super Admin ផ្សេងទៀតចង់លុបក៏ដោយ)
        if ($userToDelete->email === config('app.super_admin_email')) {
            return $this->sendError('Unauthorized: The primary system owner account cannot be deleted.', [], 403);
        }

        // 🌟 កែប្រែ៖ ការពារការលុប Admin ឬ Super Admin ពីសំណាក់ Admin ធម្មតា
        if (($userToDelete->role === 'admin' || $userToDelete->isSuperAdmin()) && !$currentUser->isSuperAdmin()) {
            return $this->sendError('Unauthorized: Only Super Admin can delete other admins.', [], 403);
        }

        // ការពារការលុបខ្លួនឯង
        if ($currentUser->id === $userToDelete->id) {
            return $this->sendError('You cannot delete your own account.', [], 403);
        }

        $userToDelete->delete();

        return $this->sendResponse([], 'User deleted successfully.');
    }
}
