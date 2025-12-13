<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class EnsureTenantIsSet
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->guard('sanctum')->check()) {
            $user = auth()->guard('sanctum')->user();
            
            // Vérifier que le tenant_id correspond à l'en-tête
            $tenant_id_header = $request->header('X-Tenant-ID');
            
            if ($tenant_id_header && $tenant_id_header != $user->tenant_id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return $next($request);
    }
}
