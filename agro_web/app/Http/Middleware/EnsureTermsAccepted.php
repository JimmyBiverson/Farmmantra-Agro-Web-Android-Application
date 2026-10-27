<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Franchise API calls are refused until the current terms & conditions have
 * been accepted (GET /api/terms, POST /api/terms/accept stay available).
 */
class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->needsToAcceptTerms()) {
            return response()->json([
                'message' => 'Please read and accept the terms and conditions to continue.',
                'code' => 'terms_required',
            ], 403);
        }

        return $next($request);
    }
}
