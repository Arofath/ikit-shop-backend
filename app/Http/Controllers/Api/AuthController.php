<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\AdminLoginOtpMail;
use App\Mail\ForgotPasswordOtpMail;
use App\Mail\RegisterOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

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

            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false);

            if ($bypassOtp) {
                $user->update(['email_verified_at' => now()]);
                $token = $user->createToken('api_token')->plainTextToken;

                $data = [
                    'user' => new UserResource($user->load('profile')),
                    'token' => $token
                ];
                return $this->sendResponse($data, 'Local Testing: Admin 2FA Bypassed. Login successful.', 200);
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

            return $this->sendError("Invalid credentials. You have " . max(0, $remaining) . " attempts remaining.", ['attempts_left' => max(0, $remaining)], 422);
        }

        if (!$user->is_active) {
            return $this->sendError('Your account is disabled.', [], 403);
        }

        // 🌟 កែប្រែទី១៖ អនុញ្ញាតឱ្យទាំង admin និង super_admin អាចរំលងការ Verify Email ទីនេះបាន
        if ($user->email_verified_at === null && !in_array($user->role, ['admin', 'super_admin'])) {
            return $this->sendError('Your email address is not verified. Please verify your email before logging in.', ['needs_verification' => true, 'email' => $user->email], 403);
        }

        RateLimiter::clear($throttleKey);
        $user->update(['last_login_at' => now()]);

        // 🌟 កែប្រែទី២៖ អនុញ្ញាតឱ្យទាំង admin និង super_admin ចូលក្នុងដំណើរការ OTP / Bypass នេះ
        if (in_array($user->role, ['admin', 'super_admin'])) {
            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false);

            // 🌟 បន្ថែមថ្មី៖ ឆែកមើលថាតើប្រព័ន្ធ Bypass ត្រូវបានបើក ឬ Admin បានបិទ 2FA ដោយខ្លួនឯងឬទេ
            if ($bypassOtp || $user->is_2fa_enabled == false) {

                if ($user->email_verified_at === null) {
                    $user->update(['email_verified_at' => now()]);
                }

                // 🌟 គួរប្រើ 'admin_api_token' ឱ្យដូចទៅនឹងមុខងារ verifyAdminLogin ដែរ
                $token = $user->createToken('admin_api_token')->plainTextToken;

                $data = ['user' => new UserResource($user->load('profile')), 'token' => $token];
                return $this->sendResponse($data, 'Login successful. (2FA Disabled)', 200);
            }

            // ហៅប្រើ Helper Method ប្រសិនបើ 2FA ត្រូវបានបើក
            $otpCode = $this->generateAndSaveOtp($user, 'login');
            Mail::to($user->email)->send(new AdminLoginOtpMail($otpCode));

            $data = ['requires_2fa' => true, 'email' => $user->email, 'expires_in' => self::OTP_EXPIRY_TEXT];
            return $this->sendResponse($data, 'Admin credentials verified. OTP is required.', 200);
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

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return $this->sendError('Unauthorized access.', [], 403);
        }

        // ហៅប្រើ Helper Method
        $validation = $this->validateOtpProcess($user, $request->otp_code, 'login');
        if (!$validation['isValid']) {
            return $this->sendError($validation['message'], [], $validation['status']);
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
            return $this->sendError($validation['message'], [], $validation['status']);
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
            return $this->sendError('Account already verified.', [], 400);
        }

        $latestOtp = $user->otps()->where('purpose', 'register')->latest()->first();
        if ($latestOtp && $latestOtp->created_at->addMinute() > now()) {
            $secondsLeft = 60 - $latestOtp->created_at->diffInSeconds(now());
            return $this->sendError("Please wait {$secondsLeft} seconds.", [], 429);
        }

        return DB::transaction(function () use ($user) {
            // ហៅប្រើ Helper Method
            $otpCode = $this->generateAndSaveOtp($user, 'register');

            $bypassOtp = env('BYPASS_OTP_ON_LOCAL', false);
            if (!$bypassOtp) {
                Mail::to($user->email)->send(new RegisterOtpMail($otpCode));
            }

            $data = ['expires_in' => self::OTP_EXPIRY_TEXT];
            return $this->sendResponse($data, 'A new OTP has been sent.', 200);
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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // 🌟 បន្ថែមថ្មី៖ បិទមិនឱ្យ Admin ប្រើប្រាស់ Route របស់ Customer
        if (in_array($user->role, ['admin', 'super_admin'])) {
            return $this->sendError('Please use the admin portal to reset your password.', [], 403);
        }

        if (empty($user->password)) {
            return $this->sendError(
                'Your account is linked with Google. Please return to the login page and click "Continue with Google".',
                ['is_social_login' => true],
                400
            );
        }

        return DB::transaction(function () use ($user) {
            $otpCode = $this->generateAndSaveOtp($user, 'password_reset');
            Mail::to($user->email)->send(new ForgotPasswordOtpMail($otpCode));

            $data = ['expires_in' => self::OTP_EXPIRY_TEXT];
            return $this->sendResponse($data, 'Password reset OTP has been sent to your email.', 200);
        });
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        // 🌟 បន្ថែមថ្មី៖ បិទមិនឱ្យ Admin ប្រើប្រាស់ Route របស់ Customer
        if (in_array($user->role, ['admin', 'super_admin'])) {
            return $this->sendError('Unauthorized access.', [], 403);
        }

        $validation = $this->validateOtpProcess($user, $request->otp_code, 'password_reset');

        if (!$validation['isValid']) {
            return $this->sendError($validation['message'], [], $validation['status']);
        }

        return DB::transaction(function () use ($user, $validation, $request) {
            $validation['otp']->update(['is_used' => true]);
            $user->update(['password' => Hash::make($request->password)]);

            return $this->sendResponse([], 'Your password has been successfully reset. You can now log in with your new password.', 200);
        });
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        // 🌟 បន្ថែម៖ ទប់ស្កាត់គណនី Social Login ដែលគ្មានលេខសម្ងាត់ដើម
        if (empty($user->password)) {
            return $this->sendError('Your account is linked with Google and does not have a password to change.', [], 400);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->sendError('Current password is incorrect.', [], 422);
        }

        // 🌟 ស្រេចចិត្ត៖ ការពារកុំឱ្យគេប្តូរលេខសម្ងាត់ថ្មី ដូចលេខសម្ងាត់ចាស់
        if (Hash::check($request->new_password, $user->password)) {
            return $this->sendError('New password cannot be the same as your current password.', [], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return $this->sendResponse([], 'Password updated successfully.', 200);
    }

    // ==========================================
    // 🛡️ ADMIN PASSWORD RESET METHODS
    // ==========================================

    public function adminForgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // 🌟 ១. ពិនិត្យ Role: អនុញ្ញាតតែ Admin & Super Admin ប៉ុណ្ណោះ
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return $this->sendError('Unauthorized access. This endpoint is for administrators only.', [], 403);
        }

        if (empty($user->password)) {
            return $this->sendError(
                'Your account is linked with Google. Please contact Super Admin for assistance.',
                ['is_social_login' => true],
                400
            );
        }

        return DB::transaction(function () use ($user) {
            // 🌟 ២. ប្រើគោលបំណងថ្មី 'admin_password_reset' ដើម្បីកុំឱ្យច្រឡំជាមួយ Customer
            $otpCode = $this->generateAndSaveOtp($user, 'admin_password_reset');

            // ផ្ញើ Email (អ្នកអាចបង្កើត AdminForgotPasswordOtpMail ថ្មីមួយទៀតនៅថ្ងៃក្រោយបើចង់រចនា Template ផ្សេង)
            Mail::to($user->email)->send(new ForgotPasswordOtpMail($otpCode));

            $data = ['expires_in' => self::OTP_EXPIRY_TEXT];
            return $this->sendResponse($data, 'Admin password reset OTP has been sent to your email.', 200);
        });
    }

    public function adminResetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return $this->sendError('Unauthorized access.', [], 403);
        }

        // 🌟 ៣. ផ្ទៀងផ្ទាត់ OTP ជាមួយនឹងគោលបំណង 'admin_password_reset'
        $validation = $this->validateOtpProcess($user, $request->otp_code, 'admin_password_reset');

        if (!$validation['isValid']) {
            return $this->sendError($validation['message'], [], $validation['status']);
        }

        return DB::transaction(function () use ($user, $validation, $request) {
            $validation['otp']->update(['is_used' => true]);
            $user->update(['password' => Hash::make($request->password)]);

            return $this->sendResponse([], 'Admin password has been successfully reset. You can now log in securely.', 200);
        });
    }

    // ==========================================
    // 🛡️ SECURITY SETTINGS
    // ==========================================
    public function toggle2FA(Request $request)
    {
        $request->validate([
            'is_2fa_enabled' => 'required|boolean',
        ]);

        $user = $request->user();

        // អនុញ្ញាតឱ្យតែ Admin និង Super Admin ប៉ុណ្ណោះដែលអាចបិទ/បើកមុខងារនេះបាន
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return $this->sendError('Unauthorized to change 2FA settings.', [], 403);
        }

        $user->update([
            'is_2fa_enabled' => $request->is_2fa_enabled
        ]);

        $status = $request->is_2fa_enabled ? 'enabled' : 'disabled';

        return $this->sendResponse(
            ['is_2fa_enabled' => $user->is_2fa_enabled],
            "Two-Factor Authentication has been successfully {$status}.",
            200
        );
    }
}
