<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        /**
         * ប្រសិនបើគាត់ជា Super Admin (Email ត្រូវ) 
         * គាត់អាចចូលបានគ្រប់ Route ទាំងអស់ដែលមានជាប់ Middleware Role នេះ
         */
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // សម្រាប់ Admin ធម្មតា ឬ Customer គឺឆែកតាម Column role ក្នុង DB ធម្មតា
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission.'
            ], 403);
        }

        return $next($request);
    }
}
