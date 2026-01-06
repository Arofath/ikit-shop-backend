<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    // List users
    public function index(Request $request)
    {
        $users = User::query()
            ->when(
                $request->role,
                fn($q) =>
                $q->where('role', $request->role)
            )
            ->when(
                $request->is_active !== null,
                fn($q) =>
                $q->where('is_active', $request->is_active)
            )
            ->when(
                $request->search,
                fn($q) =>
                $q->where(function ($qq) use ($request) {
                    $qq->where('name', 'ilike', "%{$request->search}%")
                        ->orWhere('email', 'ilike', "%{$request->search}%")
                        ->orWhere('phone_number', 'ilike', "%{$request->search}%");
                })
            )
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'List of users',
            'data' => $users,
        ]);
    }

    // view user details
    public function show(string $id)
    {
        $user = User::with('customerProfile')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'User details',
            'data' => $user,
        ]);
    }

    // Enable / Disable user
    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'is_active' => $request->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated',
            'data' => $user,
        ]);
    }

    // Change role
    public function updateRole(Request $request, string $id)
    {
        $request->validate([
            'role' => 'required|in:admin,customer',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'role' => $request->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User role updated',
            'data' => $user,
        ]);
    }

    // Delete user
    public function destroy(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        // Prevent admin from deleting self
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }
}
