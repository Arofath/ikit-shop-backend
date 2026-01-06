<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SupabaseStorageService;

class CustomerProfileController extends Controller
{
    // Get customer profile
    public function show(Request $request)
    {
        $profile = $request->user()->customerProfile;

        return response()->json($profile);
    }

    // Update customer profile
    public function update(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
        ]);

        $user = $request->user();

        // update user name (users table)
        if ($request->filled('name')) {
            $user->update(['name' => $request->name]);
        }

        // Update profile data (customer_profiles table)
        $profile = $user->customerProfile;
        $profile->update(
            $request->only('gender')
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'phone_number']),
                'profile' => $profile,
            ],
        ]);
    }

    public function uploadImage(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'image' => 'required|image|max:2048',
        ]);

        $user = $request->user();
        $profile = $user->customerProfile;

        $publicUrl = $storage->uploadImage(
            file: $request->file('image'),
            bucket: env('SUPABASE_BUCKET'),
            oldImageUrl: $profile->profile_image,
            prefix: $request->user()->id
        );

        // save to DB
        $profile->update([
            'profile_image' => $publicUrl,
        ]);

        return response()->json([
            'message' => 'Profile image uploaded successfully',
            'profile_image' => $publicUrl,
        ]);
    }
}
