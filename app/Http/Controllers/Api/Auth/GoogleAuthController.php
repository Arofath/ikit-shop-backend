<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Exception;

class GoogleAuthController extends Controller
{
    /**
     * 🌟 ១. បាញ់ Link របស់ Google ទៅឱ្យ Vue Frontend
     */
    public function redirect()
    {
        // ប្រើ stateless() ព្រោះយើងសរសេរ API (គ្មាន Session ទេ)
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }

    /**
     * 🌟 ២. ទទួលទិន្នន័យត្រឡប់មកពី Google វិញ
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // ស្វែងរក User ក្នុង Database តាមរយៈ Email
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // 🛡️ លក្ខខណ្ឌទី ១៖ អនុញ្ញាតឱ្យតែ Customer ប៉ុណ្ណោះដែលអាច Login តាម Storefront ចូលបាន
                if ($user->role !== 'customer') {
                    // អាចប្តូរសារ Error ឱ្យទូទៅជាងមុន ដើម្បីកុំឱ្យគេដឹងថា Role អ្វី
                    return redirect(env('FRONTEND_URL') . '/login?error=unauthorized_role');
                }

                // 🔗 លក្ខខណ្ឌទី ២៖ ធ្លាប់មាន Account តែអត់ទាន់ភ្ជាប់ Google
                if (empty($user->provider_id)) {
                    $user->update([
                        'provider' => 'google',
                        'provider_id' => $googleUser->getId(),
                        'email_verified_at' => now(), // ចាត់ទុកថា Verify ហើយ
                    ]);
                }

                // Update ម៉ោង Login ចុងក្រោយ
                $user->update(['last_login_at' => now()]);
            } else {
                // 🌟 លក្ខខណ្ឌទី ៣៖ អ្នកប្រើប្រាស់ថ្មីស្រឡាង (Register)
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'role' => 'customer',
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'password' => null, // គ្មាន Password ទេ
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'last_login_at' => now(),
                ]);
            }

            // 🌟 បង្កើត Sanctum Token (អ្នកធ្លាប់ដាក់ HasApiTokens ក្នុង Model រួចហើយ)
            $token = $user->createToken('storefront-token')->plainTextToken;

            // 🌟 បញ្ជូនអតិថិជនត្រឡប់ទៅ Vue វិញ ព្រមជាមួយ Token តាមរយៈ URL
            return redirect(env('FRONTEND_URL') . '/auth/google/callback?token=' . $token);
        } catch (Exception $e) {
            // បើមាន Error អ្វីមួយ បាញ់ត្រឡប់ទៅ Vue វិញជាមួយសារ Error
            return redirect(env('FRONTEND_URL') . '/login?error=auth_failed');
        }
    }
}
