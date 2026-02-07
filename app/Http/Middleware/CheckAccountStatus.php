<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ឆែកមើលតែ User ដែលបាន Login ហើយប៉ុណ្ណោះ
        if ($request->user() && !$request->user()->is_active) {
            // លុប Token ចោលភ្លាមៗ
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Your account is disabled. Please contact support.'
            ], 403);
        }

        return $next($request);
    }
}
