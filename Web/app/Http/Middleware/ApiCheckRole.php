<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiCheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        if (! empty($roles)) {
            $userRole = $user->role?->name;
            if (! in_array($userRole, $roles)) {
                return response()->json(['message' => 'Unauthorized. Insufficient permissions.'], 403);
            }
        }

        return $next($request);
    }
}
