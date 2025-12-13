<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // First check if user has any explicit role (via pivot table)
        $userRoles = $user->roles()->pluck('name')->toArray();
        
        // If no pivot roles, fall back to user.role field (for backward compat)
        if (empty($userRoles) && $user->role) {
            $userRoles = [$user->role];
        }
        
        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => 'Forbidden',
            'message' => "You don't have access to this resource"
        ], 403);
    }
}
