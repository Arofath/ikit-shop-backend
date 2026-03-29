<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RegisterOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;


class AuthController extends Controller
{
    // Customer registration
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email:rfc,dns|unique:users',
            'phone_number' => 'required|string|max:15|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return DB::transaction(function () use ($request) {

            // ១. បង្កើត User ថ្មី
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
                'role' => 'customer',
                'is_active' => true,
            ]);

            // ==========================================
            // 🌟 មុខងារ Bypass OTP សម្រាប់តែពេលសរសេរកូដ (Local Testing)
            // ==========================================
            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false) && app()->environment('local');

            if ($bypassOtp) {
                // Auto-verify គណនីភ្លាមៗ
                $user->update(['email_verified_at' => now()]);

                // បង្កើត Token ឱ្យតែម្តង ដើម្បីយកទៅប្រើប្រាស់បានភ្លាមៗ
                $token = $user->createToken('api_token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Local Testing: Registration successful & OTP Bypassed.',
                    'data' => [
                        'user' => $user,
                        'token' => $token
                    ]
                ], 201);
            }
            // ==========================================


            // ២. ដំណើរការធម្មតា (បើមិន Bypass) - បង្កើតកូដ OTP
            $otpCode = (string) random_int(100000, 999999);

            $user->otps()->create([
                'contact_type' => 'email',
                'contact_value' => $user->email,
                'otp_hash' => Hash::make($otpCode),
                'purpose' => 'register',
                'expires_at' => now()->addMinutes(5),
            ]);

            // ៣. ផ្ញើ Email (នឹងដំណើរការពេលយើងបិទ Bypass)
            Mail::to($user->email)->send(new RegisterOtpMail($otpCode));

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please check your email for the OTP code.',
                'data' => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'expires_in' => '5 minutes'
                ]
            ], 201);
        });
    }

    // Login (Email Only)
    public function login(Request $request)
    {
        // ១. ធ្វើត្រឹម Syntax Validation (email) បានហើយ ដើម្បីឱ្យ Login លឿន
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $throttleKey = 'login-attempts:' . $request->ip();

        // ២. ឆែកមើលថា តើជាប់ប្លុក (Too many attempts) ឬទេ?
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);

            // ប្រើ response()->json() ផ្ទាល់ ឬអាចបន្តប្រើ $this->sendError បើអ្នកមាន BaseController
            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Please try again after {$minutes} " . ($minutes > 1 ? 'minutes' : 'minute') . ".",
                'data' => ['retry_after_minutes' => $minutes]
            ], 429);
        }

        // ៣. ស្វែងរក User តាមតែ Email ប៉ុណ្ណោះ
        $user = User::where('email', $request->email)->first();

        // ៤. ឆែកព័ត៌មាន Login (ការពារការទាយ Password និងការពារ Timing Attack)
        if (!$user || !Hash::check($request->password, $user->password)) {
            $attempts = RateLimiter::attempts($throttleKey);
            RateLimiter::hit($throttleKey, $decaySeconds = ($attempts + 1) * 60);
            $remaining = 5 - RateLimiter::attempts($throttleKey);

            return response()->json([
                'success' => false,
                'message' => "Invalid credentials. You have " . max(0, $remaining) . " attempts remaining.",
                'data' => ['attempts_left' => max(0, $remaining)]
            ], 422);
        }

        // ៥. ឆែកស្ថានភាព Account ថា Active ឬអត់
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is disabled. Please contact support.'
            ], 403);
        }

        // ==========================================
        // 🌟 ៦. ចំណុចថ្មី៖ ឆែកមើលថា Account នេះបាន Verify Email ហើយឬនៅ?
        // ==========================================
        if ($user->email_verified_at === null) {
            return response()->json([
                'success' => false,
                'message' => 'Your email address is not verified. Please verify your email before logging in.',
                'data' => [
                    'needs_verification' => true,
                    'email' => $user->email // បញ្ជូន Email ទៅ Client វិញ ដើម្បីឱ្យពួកគេងាយស្រួលចុច "Resend OTP"
                ]
            ], 403); // 403 Forbidden ក៏ស័ក្តិសមសម្រាប់ករណីនេះដែរ
        }

        // ៧. បើ Login ជោគជ័យ និង Verify រួចរាល់ ត្រូវសម្អាត Cache នៃការព្យាយាមដែលធ្លាប់ខុសចេញ
        RateLimiter::clear($throttleKey);

        // Update ម៉ោង Login ចុងក្រោយ
        $user->update(['last_login_at' => now()]);

        // ==========================================
        // 🌟 ៨. ចំណុចថ្មី៖ ត្រៀមលក្ខណៈសម្រាប់ Admin 2FA (OTP)
        // ==========================================
        if ($user->role === 'admin') {

            // មុខងារ Bypass OTP សម្រាប់តែពេលសរសេរកូដ (Local Testing)
            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false) && app()->environment('local');

            if ($bypassOtp) {
                // បើ Bypass គឺឱ្យ Token គាត់ចូលតែម្តង មិនបាច់សុំ OTP ទេ
                $token = $user->createToken('api_token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Local Testing: Admin 2FA Bypassed. Login successful.',
                    'data' => [
                        'user' => $user->load('profile'),
                        'token' => $token
                    ]
                ], 200);
            }

            // ដំណើរការធម្មតា (មិន Bypass)
            // ក. បិទកូដ Login ចាស់ៗដែលគាត់ធ្លាប់ Request តែមិនទាន់ប្រើ
            $user->otps()
                ->where('purpose', 'login') // សម្គាល់ថាជា OTP សម្រាប់ Login
                ->where('is_used', false)
                ->update(['is_used' => true]);

            // ខ. បង្កើតកូដថ្មី
            $otpCode = (string) random_int(100000, 999999);

            $user->otps()->create([
                'contact_type' => 'email',
                'contact_value' => $user->email,
                'otp_hash' => Hash::make($otpCode),
                'purpose' => 'login', // ដូរ Purpose ទៅជា Login
                'expires_at' => now()->addMinutes(5),
            ]);

            // គ. ផ្ញើ Email (យើងនឹងបង្កើត Mail Class នៅខាងក្រោម)
            Mail::to($user->email)->send(new \App\Mail\AdminLoginOtpMail($otpCode));

            // ឃ. បញ្ជូន Response ដោយគ្មាន Token តែប្រាប់ឱ្យគាត់ទៅយកកូដ
            return response()->json([
                'success' => true,
                'message' => 'Admin credentials verified. OTP is required to complete login.',
                'data' => [
                    'requires_2fa' => true,
                    'email' => $user->email,
                    'expires_in' => '5 minutes'
                ]
            ], 200);
        }

        // ៩. សម្រាប់ Customer គឺឱ្យ Token យកទៅប្រើតែម្តង
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user->load('profile'), // អាចប្តូរទៅប្រើ new UserResource() វិញបើអ្នកមាន
                'token' => $token
            ]
        ], 200);
    }

    // ដំណាក់កាលទី ៩៖ បញ្ជាក់លេខកូដ OTP សម្រាប់ Admin Login (Verify Admin 2FA)
    public function verifyAdminLogin(Request $request)
    {
        // ១. Validation
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        // ២. ការពារសុវត្ថិភាពទ្វេដង៖ មិនអនុញ្ញាតឱ្យ Customer មកប្រើ Route នេះទេ
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        // ៣. ទាញយក OTP ចុងក្រោយបង្អស់សម្រាប់គោលបំណង 'login'
        $otp = $user->otps()
            ->where('purpose', 'login') // សំខាន់បំផុត៖ ត្រូវប្រាកដថាជាកូដសម្រាប់ Login
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP code is invalid or has expired. Please return to the login page to request a new one.'
            ], 400);
        }

        // ៤. ឆែកមើលចំនួនដងនៃការវាយខុស (ទប់ស្កាត់ការទាយកូដ)
        if ($otp->attempts >= 5) {
            $otp->update(['is_used' => true]);
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. This OTP has been invalidated.'
            ], 429);
        }

        // ៥. ផ្ទៀងផ្ទាត់ Hash របស់ OTP
        if (!Hash::check($request->otp_code, $otp->otp_hash)) {
            $otp->increment('attempts');
            $attemptsLeft = 5 - $otp->attempts;
            return response()->json([
                'success' => false,
                'message' => "Invalid OTP code. You have {$attemptsLeft} attempts left."
            ], 400);
        }

        // ៦. ដំណើរការជោគជ័យ៖ ផ្តល់ Token ឱ្យ Admin ចូលប្រព័ន្ធ
        return DB::transaction(function () use ($user, $otp) {
            // Update ស្ថានភាព OTP ថាបានប្រើប្រាស់រួច
            $otp->update(['is_used' => true]);

            // បង្កើត Token សម្រាប់ Admin (អាចដាក់ឈ្មោះ Token ឱ្យប្លែកពី Customer បន្តិចក៏បាន)
            $token = $user->createToken('admin_api_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Admin 2FA verified successfully. Welcome to the dashboard.',
                'data' => [
                    'user' => $user->load('profile'),
                    'token' => $token
                ]
            ], 200);
        });
    }

    public function verifyOtp(Request $request)
    {
        // ១. ធ្វើការ Validation ទិន្នន័យដែលបញ្ជូនមក
        $request->validate([
            'email' => 'required|email|exists:users,email', // ត្រូវប្រាកដថា Email នេះមានក្នុង System
            'otp_code' => 'required|string|size:6', // កូដត្រូវតែមាន ៦ ខ្ទង់
        ]);

        $user = User::where('email', $request->email)->first();

        // ២. ទាញយក OTP ចុងក្រោយបង្អស់ (Latest) ដែលនៅមានសុពលភាពសម្រាប់ User នេះ
        $otp = $user->otps()
            ->where('purpose', 'register')
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest() // យកកូដដែលទើបតែ Generate ចុងក្រោយគេ បើគាត់ Request ច្រើនដង
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP code is invalid or has expired. Please request a new one.'
            ], 400);
        }

        // ៣. ឆែកមើលចំនួនដងនៃការវាយខុស (ទប់ស្កាត់ការទាយកូដ)
        if ($otp->attempts >= 5) {
            // បើខុស ៥ ដង យើងបិទកូដនេះចោលតែម្តង ដើម្បីសុវត្ថិភាព
            $otp->update(['is_used' => true]);
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. This OTP has been invalidated. Please request a new one.'
            ], 429);
        }

        // ៤. ផ្ទៀងផ្ទាត់ Hash របស់ OTP ជាមួយកូដដែល User បញ្ចូលមក
        if (!Hash::check($request->otp_code, $otp->otp_hash)) {
            // បើខុស យើងបង្កើនចំនួន attempts ១ 
            $otp->increment('attempts');

            $attemptsLeft = 5 - $otp->attempts;
            return response()->json([
                'success' => false,
                'message' => "Invalid OTP code. You have {$attemptsLeft} attempts left."
            ], 400);
        }

        // ៥. ដំណើរការជោគជ័យ (Verify Success Flow)
        return DB::transaction(function () use ($user, $otp) {
            // Update ស្ថានភាព OTP ថាបានប្រើប្រាស់រួច
            $otp->update(['is_used' => true]);

            // Update ស្ថានភាព User ឱ្យក្លាយជា Verified
            $user->update(['email_verified_at' => now()]);

            // បង្កើត Token សម្រាប់ឱ្យគាត់ Login ចូលប្រើប្រាស់
            $token = $user->createToken('api_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Account verified successfully.',
                'data' => [
                    'user' => $user, // អាចប្រើ UserResource បើអ្នកមានរៀបចំ
                    'token' => $token
                ]
            ], 200);
        });
    }

    public function resendOtp(Request $request)
    {
        // ធ្វើការ Validation ត្រូវប្រាកដថា Email នេះមានក្នុងប្រព័ន្ធ
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // ១. ឆែកមើលថា តើគណនីនេះ Verify រួចហើយឬនៅ?
        if ($user->email_verified_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is already verified. You can login directly.'
            ], 400);
        }

        // ២. ការពារការ Spam (Cooldown 1 នាទី)
        $latestOtp = $user->otps()->where('purpose', 'register')->latest()->first();
        if ($latestOtp && $latestOtp->created_at->addMinute() > now()) {
            $secondsLeft = 60 - $latestOtp->created_at->diffInSeconds(now());
            return response()->json([
                'success' => false,
                'message' => "Please wait {$secondsLeft} seconds before requesting a new OTP."
            ], 429); // 429 Too Many Requests
        }

        // ៣. ប្រើប្រាស់ Transaction ដើម្បីធានាសុវត្ថិភាពទិន្នន័យ
        return DB::transaction(function () use ($user) {

            // បិទកូដចាស់ៗដែលមិនទាន់ប្រើ និងមិនទាន់ផុតកំណត់ ដើម្បីកុំឱ្យជាន់គ្នា
            $user->otps()
                ->where('purpose', 'register')
                ->where('is_used', false)
                ->update(['is_used' => true]);

            // បង្កើតលេខកូដថ្មី
            $otpCode = (string) random_int(100000, 999999);

            $user->otps()->create([
                'contact_type' => 'email',
                'contact_value' => $user->email,
                'otp_hash' => Hash::make($otpCode),
                'purpose' => 'register',
                'expires_at' => now()->addMinutes(5),
            ]);

            // ឆែកមើលលក្ខខណ្ឌ Bypass ដូចដែលយើងធ្លាប់ធ្វើនៅកន្លែង Register
            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false) && app()->environment('local');

            if (!$bypassOtp) {
                Mail::to($user->email)->send(new RegisterOtpMail($otpCode));
            }

            return response()->json([
                'success' => true,
                'message' => 'A new OTP has been sent to your email.',
                'data' => [
                    'expires_in' => '5 minutes'
                ]
            ], 200);
        });
    }

    // Logout
    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            // លុប Token ដែលកំពុងប្រើប្រាស់បច្ចុប្បន្ន
            $user->tokens()->where('id', $token->id)->delete();

            return $this->sendResponse([], 'Logged out successfully.');
        }

        return $this->sendError('Unauthenticated or no token found.', [], 401);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->sendError('Current password is incorrect.', [], 422);
        }

        $user->update(['password' => $request->new_password]);

        return $this->sendResponse([], 'Password updated successfully.');
    }
}