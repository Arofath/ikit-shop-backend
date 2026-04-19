<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Services\CloudinaryStorageService;
use Illuminate\Support\Facades\Cache; // 🌟 ហៅ Cache មកប្រើដើម្បីឱ្យដើរលឿន

class SettingController extends Controller
{
    // ទាញយក Settings ទាំងអស់ (Group តាមប្រភេទ Tab)
    public function index()
    {
        // ប្រើប្រាស់ Cache ដើម្បីកុំឱ្យ Query ញឹកញាប់ពេក រក្សាទុក ១ ថ្ងៃ
        $settings = Cache::remember('app_settings', 86400, function () {
            // ទាញយកទិន្នន័យទាំងអស់ ហើយចងវាជា Array ដែលមាន Key ជាឈ្មោះ group (general, social, ...)
            return Setting::all()->groupBy('group');
        });

        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved successfully.',
            'data' => $settings
        ], 200);
    }

    // Save ឬ Update Settings ច្រើនក្នុងពេលតែមួយ
    public function update(Request $request, CloudinaryStorageService $storage)
    {
        $request->validate([
            'settings'   => 'required|array',
            'settings.*' => 'nullable' // អនុញ្ញាតឱ្យទិន្នន័យទទេបាន
        ]);

        $settings = $request->input('settings');

        foreach ($settings as $key => $value) {
            // ប្រសិនបើ Value នោះគឺជា File (ដូចជា Logo) យើងត្រូវ Upload សិន
            if ($request->hasFile("settings.{$key}")) {
                $file = $request->file("settings.{$key}");

                // ទាញយករូបចាស់ (បើមាន) ដើម្បីលុបចេញពី Cloudinary វិញ
                $oldSetting = Setting::where('key', $key)->first();
                $oldImageUrl = $oldSetting ? $oldSetting->value : null;

                $uploadedUrl = $storage->uploadImage(
                    file: $file,
                    folder: 'settings',
                    oldImageUrl: $oldImageUrl
                );

                $value = $uploadedUrl;
            }

            // Save ឬ Update ចូល Database (ប្រើ updateOrCreate ដោយឆែកតាម key)
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
                // ចំណាំ៖ យើងអត់ Update `group` ទេ ព្រោះយើងនឹងបញ្ចូល group ដំបូងតាមរយៈ Seeder ឬ Frontend
            );
        }

        // លុប Cache ចាស់ចោល ពេលមានការ Update ថ្មី
        Cache::forget('app_settings');

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => Setting::all()->groupBy('group') // បាញ់ទិន្នន័យថ្មីទៅវិញ
        ], 200);
    }
}
