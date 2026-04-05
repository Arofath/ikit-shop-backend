<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\CloudinaryStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserProfileController extends Controller
{
    // Get user profile
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'success' => true,
            'message' => 'Profile fetched successfully.',
            'data'    => new UserResource($user)
        ], 200);
    }

    // Update user profile
    public function update(Request $request)
    {
        $request->validate([
            'name'          => 'nullable|string|max:255',
            'gender'        => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'address'       => 'nullable|string|max:500',
            'position'      => 'nullable|string|max:100',
            'bio'           => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate([]);

        try {
            DB::transaction(function () use ($request, $user, $profile) {
                if ($request->filled('name')) {
                    $user->update(['name' => $request->name]);
                }

                // Update profile ជាមួយ field ទាំងអស់
                $profile->update($request->only([
                    'gender',
                    'date_of_birth',
                    'address',
                    'position',
                    'bio'
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data'    => new UserResource($user->fresh('profile'))
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed.',
                'errors'  => app()->environment('local') ? ['error' => $e->getMessage()] : []
            ], 500);
        }
    }

    public function uploadImage(Request $request, CloudinaryStorageService $storage)
    {
        set_time_limit(120);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate([]);

        try {
            // កំណត់ទំហំសម្រាប់តែ Profile
            $profileTransformations = [
                'width' => 500,
                'height' => 500,
                'crop' => 'fill',
                'gravity' => 'face',
                'quality' => 'auto:best',
                'fetch_format' => 'auto'
            ];

            // 🌟 ហៅ Service មកប្រើ ត្រឹមតែ ១ បន្ទាត់!
            $publicUrl = $storage->uploadImage(
                file: $request->file('image'),
                folder: 'profiles',
                oldImageUrl: $profile->profile_image,
                transformations: $profileTransformations
            );

            // រក្សាទុក URL ចូល Database
            $profile->update([
                'profile_image' => $publicUrl,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile image uploaded and optimized successfully.',
                'data'    => new UserResource($user->fresh('profile')),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Image upload failed.',
                'errors'  => app()->environment('local') ? ['error' => $e->getMessage()] : []
            ], 500);
        }
    }
}
