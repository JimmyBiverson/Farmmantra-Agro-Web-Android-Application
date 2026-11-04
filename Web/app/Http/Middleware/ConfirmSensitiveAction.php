<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security process for destructive admin actions: the administrator must
 * re-enter their password and give a reason. Every attempt is audit-logged.
 */
class ConfirmSensitiveAction
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $password = (string) $request->input('confirm_password', '');
        $reason = trim((string) $request->input('action_reason', ''));
        $route = $request->route()?->getName() ?? $request->path();

        if ($password === '' || ! Hash::check($password, $user->password)) {
            ActivityLogger::log('security.confirmation_failed', "Failed confirmation for {$route}", null, $user->id, ['target_id' => $request->input('id')]);

            return back()->with('error', 'Action cancelled: your password was not confirmed.');
        }

        if (mb_strlen($reason) < 5) {
            return back()->with('error', 'Action cancelled: please give a reason (at least 5 characters).');
        }

        ActivityLogger::log('security.confirmed_action', "Confirmed {$route}: {$reason}", null, $user->id, [
            'target_id' => $request->input('id'),
            'reason' => $reason,
        ]);

        return $next($request);
    }
}
