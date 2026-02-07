<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\DB;

class UserProfileController extends Controller
{
    // Get user profile
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');
        // ហៅប្រើ function ពី Base Controller
        return $this->sendResponse(new UserResource($user), 'Profile fetched successfully.');
    }

    // Update user profile
    public function update(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female,other',
        ]);

        $user = $request->user();
        // បង្ការករណីបាត់ Profile (ឧទាហរណ៍ Admin ចាស់ដែលមិនទាន់មាន row ក្នុង DB)
        $profile = $user->profile()->firstOrCreate([]);

        try {
            DB::transaction(function () use ($request, $user, $profile) {
                if ($request->filled('name')) {
                    $user->update(['name' => $request->name]);
                }
                $profile->update($request->only('gender'));
            });

            return $this->sendResponse(new UserResource($user->fresh('profile')), 'Profile updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Update failed.', ['error' => $e->getMessage()], 500);
        }
        
    }

    public function uploadImage(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'image' => 'required|image|max:2048',
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate([]);

        $publicUrl = $storage->uploadImage(
            file: $request->file('image'),
            bucket: config('services.supabase.bucket_avatar'),
            oldImageUrl: $profile->profile_image,
            prefix: 'profiles/' . $user->id // រៀបចំ folder ក្នុង storage ឱ្យមានរបៀប
        );

        // save to DB
        $profile->update([
            'profile_image' => $publicUrl,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile image uploaded successfully',
            'data'    => [
                'profile_image' => $publicUrl
            ],
        ]);
    }
}
