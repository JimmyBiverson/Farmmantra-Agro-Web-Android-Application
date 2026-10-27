<?php

namespace App\Http\Controllers\Web;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs a service call from a web form and turns business-rule failures into
 * a flash error instead of an exception page.
 */
trait HandlesWebActions
{
    protected function attempt(callable $action, string|callable $success)
    {
        try {
            $result = $action();
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first() ?? $e->getMessage());
        } catch (AuthorizationException $e) {
            return back()->with('error', $e->getMessage() ?: 'You are not allowed to do that.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        return back()->with('success', is_callable($success) ? $success($result) : $success);
    }
}
