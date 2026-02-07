<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;


class AuthController extends Controller
{
    // Customer registration

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'phone_number' => 'required|string|max:15|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => $request->password,
                'role' => 'customer',
            ]);

            $token = $user->createToken('api_token')->plainTextToken;

            // ប្រើ sendResponse ពី Base Controller
            return $this->sendResponse([
                'user' => new UserResource($user->load('profile')),
                'token' => $token
            ], 'Registration successful.', 201);
        });
    }

    // Login (email or phone)
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required', // can be email or phone_number
            'password' => 'required|string',
        ]);

        // បង្កើត Key សម្រាប់សម្គាល់ User តាមរយៈ IP ឬ Email
        $throttleKey = 'login-attempts:' . $request->ip();

        // ១. ឆែកមើលថា តើជាប់ប្លុក (Too many attempts) ឬទេ?
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);

            return $this->sendError(
                "Too many login attempts. Please try again after {$minutes} " . ($minutes > 1 ? 'minutes' : 'minute') . ".",
                ['retry_after_minutes' => $minutes],
                429
            );
        }

        $user = User::where('email', $request->login)
            ->orWhere('phone_number', $request->login)
            ->first();

        // ការពារការទាយ Password និងការពារ Timing Attack
        // ២. ឆែកព័ត៌មាន Login
        if (!$user || !Hash::check($request->password, $user->password)) {

            /** * ៣. បង្កើនចំនួនដងនៃការវាយខុស និងកំណត់រយៈពេលប្លុកឱ្យកើនឡើង (Backoff Logic)
             * លើកទី១ ខុស: ប្លុកកើនតាមចំនួនដង (ឧទាហរណ៍: ខុសលើកទី៥ ប្លុក ៥នាទី)
             */
            $attempts = RateLimiter::attempts($throttleKey);
            RateLimiter::hit($throttleKey, $decaySeconds = ($attempts + 1) * 60);

            $remaining = 5 - RateLimiter::attempts($throttleKey);

            return $this->sendError(
                "Invalid credentials. You have " . max(0, $remaining) . " attempts remaining.",
                ['attempts_left' => max(0, $remaining)],
                422
            );
        }
        // ៤. ឆែកស្ថានភាព Account
        if (! $user->is_active) {
            return $this->sendError('Your account is disabled. Please contact support.', [], 403);
        }

        // ៥. បើ Login ជោគជ័យ ត្រូវសម្អាត Cache នៃការព្យាយាមដែលធ្លាប់ខុសចេញ
        RateLimiter::clear($throttleKey);

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('api_token')->plainTextToken;

        return $this->sendResponse([
            'user' => new UserResource($user->load('profile')),
            'token' => $token
        ], 'Login successful.');
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