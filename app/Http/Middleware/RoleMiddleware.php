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
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // ប្រសិនបើគាត់ជា Super Admin (តាមរយៈ Method របស់អ្នក) អនុញ្ញាតឱ្យចូលដោយស្វ័យប្រវត្តិ
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // 🌟 បំបែកអក្សរ 'admin|super_admin' ទៅជា Array ['admin', 'super_admin']
        $roles = is_array($role) ? $role : explode('|', $role);

        // 🌟 ប្រើប្រាស់មុខងារ hasAnyRole របស់ Spatie ដើម្បីឆែកមើលសិទ្ធិ
        // ឬ ប្រើការឆែក Column ចាស់ (ទុកទាំងពីរដើម្បីការពារក្រែងលោ Spatie អត់ទាន់ដើរស្រួល)
        if (!$user->hasAnyRole($roles) && !in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You do not have permission to access this resource.'
            ], 403);
        }

        return $next($request);
    }
}
