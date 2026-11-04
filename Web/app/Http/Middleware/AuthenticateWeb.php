<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWeb
{
    /**
     * Route-name prefix => roles allowed. Order matters: the first matching
     * prefix wins, so more specific prefixes come first.
     */
    private array $roleRoutes = [
        'admin' => ['System Administrator'],
        // Sales staff monitor; the warehouse uses the same screens to act.
        'staff' => ['Farmmantra Staff', 'Warehouse', 'System Administrator'],
        'ops' => ['Warehouse', 'System Administrator'],
        'finance' => ['Finance Department'],
        'accounts' => ['Finance Department', 'System Administrator'],
        'franchise' => ['Franchise Partner'],
        'reports.stock' => ['System Administrator', 'Farmmantra Staff', 'Warehouse', 'Franchise Partner'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (! $user->is_active) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
        }

        $routeName = $request->route()->getName() ?? '';
        $userRole = $user->role?->name ?? '';

        // First login (and every new version): franchise partners must read
        // and accept the terms & conditions before using the system.
        if ($user->needsToAcceptTerms() && ! in_array($routeName, ['web.terms.show', 'web.terms.accept', 'web.logout'], true)) {
            return redirect()->route('web.terms.show');
        }

        foreach ($this->roleRoutes as $prefix => $roles) {
            if (str_starts_with($routeName, "web.{$prefix}.") || $routeName === "web.{$prefix}") {
                if (! in_array($userRole, $roles, true)) {
                    abort(403, 'Unauthorized access.');
                }
                break;
            }
        }

        return $next($request);
    }
}
