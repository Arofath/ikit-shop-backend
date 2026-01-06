<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Otp;
use App\Models\User;

class OtpController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'contact_type' => 'required|in:email,phone',
            'contact_value' => 'required|string',
            'purpose' => 'required|in:register,login,password_reset,verify',
        ]);

        // Remove old OTPs
        Otp::where('contact_type', $request->contact_type)
            ->where('contact_value', $request->contact_value)
            ->where('purpose', $request->purpose)
            ->delete();

        $otp = random_int(100000, 999999);

        Otp::create([
            'user_id' => optional(
                User::where($request->contact_type, $request->contact_value)->first()
            )->id,
            'contact_type' => $request->contact_type,
            'contact_value' => $request->contact_value,
            'otp_hash' => Hash::make($otp),
            'purpose' => $request->purpose,
            'expires_at' => now()->addMinutes(5),
        ]);

        // TODO: send email or SMS
        // Mail::to($request->contact_value)->send(new OtpMail($otp));

        return response()->json([
            'message' => 'OTP sent successfully',
        ]);
    }

        public function verify(Request $request)
    {
        $request->validate([
            'contact_type' => 'required|in:email,phone',
            'contact_value' => 'required|string',
            'purpose' => 'required|in:register,login,password_reset,verify',
            'otp' => 'required|digits:6',
        ]);

        $otpRecord = Otp::valid()
            ->purpose($request->purpose)
            ->where('contact_type', $request->contact_type)
            ->where('contact_value', $request->contact_value)
            ->first();

        if (! $otpRecord) {
            return response()->json([
                'message' => 'OTP expired or not found',
            ], 422);
        }

        if ($otpRecord->attempts >= 5) {
            return response()->json([
                'message' => 'Too many attempts',
            ], 429);
        }

        if (! Hash::check($request->otp, $otpRecord->otp_hash)) {
            $otpRecord->increment('attempts');

            return response()->json([
                'message' => 'Invalid OTP',
            ], 422);
        }

        $otpRecord->markAsUsed();

        return response()->json([
            'message' => 'OTP verified successfully',
        ]);
    }
}
