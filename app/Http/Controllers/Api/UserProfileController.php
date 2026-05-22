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
        $user = $request->user(); // ទាញយក User បច្ចុប្បន្ន

        $request->validate([
            'name'          => 'nullable|string|max:255',
            // 🌟 អនុញ្ញាតឱ្យដូរលេខទូរស័ព្ទ តែត្រូវប្រាកដថាមិនជាន់ជាមួយលេខអ្នកផ្សេង (លើកលែងតែលេខខ្លួនឯង)
            'phone_number'  => 'nullable|string|max:15|unique:users,phone_number,' . $user->id,
            'gender'        => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'address'       => 'nullable|string|max:500',
            'position'      => 'nullable|string|max:100',
            'bio'           => 'nullable|string|max:1000',
        ]);

        $profile = $user->profile()->firstOrCreate([]);

        try {
            DB::transaction(function () use ($request, $user, $profile) {
                // Update ទិន្នន័យក្នុងតារាង users (name និង phone_number)
                $userDataToUpdate = [];
                if ($request->filled('name')) {
                    $userDataToUpdate['name'] = $request->name;
                }

                // 🌟 អនុញ្ញាតឱ្យ Update លេខទូរស័ព្ទ (ពិសេសសម្រាប់អ្នកមកពី Google)
                if ($request->filled('phone_number')) {
                    // លក្ខខណ្ឌបន្ថែម៖ បើចង់បិទមិនឱ្យអ្នកដែលមានលេខហើយ ដូរលេខទៀត អាចថែម if នេះ៖
                    // if (empty($user->phone_number)) { 
                    $userDataToUpdate['phone_number'] = $request->phone_number;
                    // }
                }

                if (!empty($userDataToUpdate)) {
                    $user->update($userDataToUpdate);
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
