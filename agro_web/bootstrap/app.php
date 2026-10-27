<?php

use App\Http\Middleware\ApiCheckRole;
use App\Http\Middleware\ConfirmSensitiveAction;
use App\Http\Middleware\EnsureFranchiseActive;
use App\Http\Middleware\EnsureTermsAccepted;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.role' => ApiCheckRole::class,
            'franchise.active' => EnsureFranchiseActive::class,
            'confirm.sensitive' => ConfirmSensitiveAction::class,
            'terms.accepted' => EnsureTermsAccepted::class,
        ]);

        $middleware->prepend([
            HandleCors::class,
        ]);

        $middleware->redirectGuestsTo(fn () => '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
