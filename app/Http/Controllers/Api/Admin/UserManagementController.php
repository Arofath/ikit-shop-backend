<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    // List users
    public function index(Request $request)
    {
        $currentUser = $request->user();

        $users = User::with('profile')
            // បន្ថែម Logic ឆែកសិទ្ធិនៅទីនេះ
            ->when(!$currentUser->isSuperAdmin(), function ($query) {
                /** * ប្រសិនបើមិនមែនជា Super Admin ទេ ឱ្យឃើញតែ Customer ប៉ុណ្ណោះ 
                 * ឬឃើញតែ Admin ផ្សេងទៀត (អាស្រ័យលើគោលការណ៍របស់អ្នក)
                 */
                return $query->where('role', 'customer');
            })
            // បើមានការបញ្ជូន filter មកពី Frontend (ឧទាហរណ៍ ?role=admin)
            ->when($request->role, function ($query) use ($request) {
                return $query->where('role', $request->role);
            })
            ->when($request->is_active !== null, function ($query) use ($request) {
                return $query->where('is_active', $request->is_active);
            })
            ->when($request->search, function ($query) use ($request) {
                $search = $request->search;
                return $query->where(function ($q) use ($search) {
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
        ]);
    }
    // view user details
    public function show(string $id)
    {
        $user = User::with('profile')->findOrFail($id);

        return $this->sendResponse(new UserResource($user), 'User details');
    }

    // Enable / Disable user
    public function updateStatus(Request $request, string $id)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $user = User::findOrFail($id);

        // ការពារ Admin មិនឱ្យ Disable ខ្លួនឯង (Security Check)
        if ($request->user()->id === $user->id && !$request->is_active) {
            return $this->sendError('You cannot disable your own account.', [], 403);
        }

        $user->update(['is_active' => $request->is_active]);

        return $this->sendResponse(new UserResource($user), 'User status updated');
    }

    // Change role
    public function updateRole(Request $request, string $id)
    {
        $request->validate([
            'role' => 'required|in:admin,customer',
        ]);

        $user = User::findOrFail($id);

        // [SSDLC Guard] ការពារ Admin មិនឱ្យប្តូរ Role ខ្លួនឯង (ដើម្បីកុំឱ្យបាត់សិទ្ធិគ្រប់គ្រង)
        if ($request->user()->id === $user->id) {
            return $this->sendError('You cannot change your own role for security reasons.', [], 403);
        }

        $user->update([
            'role' => $request->role,
        ]);

        return $this->sendResponse(new UserResource($user->load('profile')), 'User role updated successfully.');
    }

    // Delete user
    public function destroy(Request $request, string $id)
    {
        $userToDelete = User::findOrFail($id);
        $currentUser = $request->user();

        // ១. កម្រិតសិទ្ធិ: មានតែ Super Admin ទេទើបអាចលុប Admin ផ្សេងទៀតបាន
        if ($userToDelete->role === 'admin' && !$currentUser->isSuperAdmin()) {
            return $this->sendError('Unauthorized: Only Super Admin can delete other admins.', [], 403);
        }

        // ២. ការពារការលុបខ្លួនឯង (ដូចដែលបានធ្វើរួច)
        if ($currentUser->id === $userToDelete->id) {
            return $this->sendError('You cannot delete your own account.');
        }

        $userToDelete->delete();
        return $this->sendResponse([], 'User deleted successfully.');
    }
}
