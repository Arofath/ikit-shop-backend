<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminLoginOtpMail;
use App\Mail\RegisterOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 5;
    private const OTP_EXPIRY_TEXT = '5 minutes';
    // ==========================================
    // 🛠 PRIVATE HELPER METHODS (ដោះស្រាយបញ្ហា Duplication)
    // ==========================================

    /**
     * បិទ OTP ចាស់ និងបង្កើត OTP ថ្មី
     */
    private function generateAndSaveOtp(User $user, string $purpose): string
    {
        $user->otps()
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $otpCode = (string) random_int(100000, 999999);

        $user->otps()->create([
            'contact_type' => 'email',
            'contact_value' => $user->email,
            'otp_hash' => Hash::make($otpCode),
            'purpose' => $purpose,
            // ប្រើ Constant នៅទីនេះ
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        return $otpCode;
    }

    /**
     * ផ្ទៀងផ្ទាត់លក្ខខណ្ឌ OTP ទាំងអស់
     */
    private function validateOtpProcess(User $user, string $otpCode, string $purpose): array
    {
        $otp = $user->otps()
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        // ច្រកចេញទី ១៖ បើគ្មាន OTP ទាល់តែសោះ
        if (!$otp) {
            return ['isValid' => false, 'message' => 'OTP code is invalid or has expired.', 'status' => 400];
        }

        $errorResult = null; // អថេរសម្រាប់ផ្ទុក Error បើមាន

        // ចងលក្ខខណ្ឌដែលនៅសល់ចូលគ្នា
        if ($otp->attempts >= 5) {
            $otp->update(['is_used' => true]);
            $errorResult = ['isValid' => false, 'message' => 'Too many failed attempts. This OTP has been invalidated.', 'status' => 429];
        } elseif (!Hash::check($otpCode, $otp->otp_hash)) {
            $otp->increment('attempts');
            $attemptsLeft = 5 - $otp->attempts;
            $errorResult = ['isValid' => false, 'message' => "Invalid OTP code. You have {$attemptsLeft} attempts left.", 'status' => 400];
        }

        // ច្រកចេញទី ២៖ បើមាន Error វានឹង Return Error, បើអត់ទេ វានឹង Return ជោគជ័យ
        return $errorResult ?? ['isValid' => true, 'otp' => $otp];
    }

    // ==========================================
    // 🚀 MAIN CONTROLLER METHODS
    // ==========================================

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email:rfc,dns|unique:users',
            'phone_number' => 'required|string|max:15|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
                'role' => 'customer',
                'is_active' => true,
            ]);

            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false) && app()->environment('local');

            if ($bypassOtp) {
                $user->update(['email_verified_at' => now()]);
                $token = $user->createToken('api_token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Local Testing: Admin 2FA Bypassed. Login successful.',
                    'data' => [
                        // 🌟 ប្រើ UserResource 
                        'user' => new UserResource($user->load('profile')),
                        'token' => $token
                    ]
                ], 200);
            }

            // ហៅប្រើ Helper Method ជំនួសឱ្យការសរសេរកូដឡើងវិញ
            $otpCode = $this->generateAndSaveOtp($user, 'register');
            Mail::to($user->email)->send(new RegisterOtpMail($otpCode));

            $data = [
                'user_id' => $user->id,
                'email' => $user->email,
                'expires_in' => self::OTP_EXPIRY_TEXT
            ];
            return $this->sendResponse($data, 'Registration successful. Please check your email for the OTP code.', 201);
        });
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $throttleKey = 'login-attempts:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $minutes = ceil(RateLimiter::availableIn($throttleKey) / 60);
            return $this->sendError(
                "Too many login attempts. Please try again after {$minutes} minutes.", 
                ['retry_after_minutes' => $minutes], 
                429
            );
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $attempts = RateLimiter::attempts($throttleKey);
            RateLimiter::hit($throttleKey, ($attempts + 1) * 60);
            $remaining = 5 - RateLimiter::attempts($throttleKey);

            return response()->json([
                'success' => false,
                'message' => "Invalid credentials. You have " . max(0, $remaining) . " attempts remaining.",
                'data' => ['attempts_left' => max(0, $remaining)]
            ], 422);
        }

        if (!$user->is_active) {
            return response()->json(['success' => false, 'message' => 'Your account is disabled.'], 403);
        }

        if ($user->email_verified_at === null && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Your email address is not verified. Please verify your email before logging in.',
                'data' => ['needs_verification' => true, 'email' => $user->email]
            ], 403);
        }

        RateLimiter::clear($throttleKey);
        $user->update(['last_login_at' => now()]);

        if ($user->role === 'admin') {
            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false) && app()->environment('local');

            if ($bypassOtp) {
                $token = $user->createToken('api_token')->plainTextToken;
                return response()->json([
                    'success' => true,
                    'message' => 'Local Testing: Admin 2FA Bypassed. Login successful.',
                    // 🌟 ប្រើ UserResource 
                    'data' => ['user' => new UserResource($user->load('profile')), 'token' => $token]
                ], 200);
            }

            // ហៅប្រើ Helper Method
            $otpCode = $this->generateAndSaveOtp($user, 'login');
            Mail::to($user->email)->send(new AdminLoginOtpMail($otpCode));

            return response()->json([
                'success' => true,
                'message' => 'Admin credentials verified. OTP is required.',
                'data' => ['requires_2fa' => true, 'email' => $user->email, 'expires_in' => self::OTP_EXPIRY_TEXT]
            ], 200);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        $data = [
            'user' => new UserResource($user->load('profile')),
            'token' => $token
        ];
        return $this->sendResponse($data, 'Login successful.', 200);
    }

    public function verifyAdminLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        // ហៅប្រើ Helper Method
        $validation = $this->validateOtpProcess($user, $request->otp_code, 'login');
        if (!$validation['isValid']) {
            return response()->json(['success' => false, 'message' => $validation['message']], $validation['status']);
        }

        return DB::transaction(function () use ($user, $validation) {
            $validation['otp']->update(['is_used' => true]);

            // ==========================================
            // 🌟 បន្ថែមថ្មី៖ បើ Admin មិនទាន់ Verify Email ទេ យើង Update ឱ្យគាត់តែម្តង
            // ==========================================
            if ($user->email_verified_at === null) {
                $user->update(['email_verified_at' => now()]);
            }

            $token = $user->createToken('admin_api_token')->plainTextToken;
            $data = [
                'user' => new UserResource($user->load('profile')),
                'token' => $token
            ];
            return $this->sendResponse($data, 'Admin 2FA verified successfully.', 200);
        });
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        // ហៅប្រើ Helper Method
        $validation = $this->validateOtpProcess($user, $request->otp_code, 'register');
        if (!$validation['isValid']) {
            return response()->json(['success' => false, 'message' => $validation['message']], $validation['status']);
        }

        return DB::transaction(function () use ($user, $validation) {
            $validation['otp']->update(['is_used' => true]);
            $user->update(['email_verified_at' => now()]);
            $token = $user->createToken('api_token')->plainTextToken;

            $data = [
                'user' => new UserResource($user->load('profile')),
                'token' => $token
            ];
            return $this->sendResponse($data, 'Account verified successfully.', 200);
        });
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at !== null) {
            return response()->json(['success' => false, 'message' => 'Account already verified.'], 400);
        }

        $latestOtp = $user->otps()->where('purpose', 'register')->latest()->first();
        if ($latestOtp && $latestOtp->created_at->addMinute() > now()) {
            $secondsLeft = 60 - $latestOtp->created_at->diffInSeconds(now());
            return response()->json(['success' => false, 'message' => "Please wait {$secondsLeft} seconds."], 429);
        }

        return DB::transaction(function () use ($user) {
            // ហៅប្រើ Helper Method
            $otpCode = $this->generateAndSaveOtp($user, 'register');

            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false) && app()->environment('local');
            if (!$bypassOtp) {
                Mail::to($user->email)->send(new RegisterOtpMail($otpCode));
            }

            return response()->json([
                'success' => true,
                'message' => 'A new OTP has been sent.',
                'data' => ['expires_in' => self::OTP_EXPIRY_TEXT]
            ], 200);
            
        });
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            $user->tokens()->where('id', $token->id)->delete();
            return $this->sendResponse(null, 'Logged out successfully.', 200);
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
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]); // កុំភ្លេច Hash password ថ្មី!

        return response()->json(['success' => true, 'message' => 'Password updated successfully.'], 200);
    }
}
