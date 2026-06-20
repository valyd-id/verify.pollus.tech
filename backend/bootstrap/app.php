<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            // Resolves the calling VerificationProject from X-API-Key / Bearer.
            'project.key' => \App\Http\Middleware\AuthenticateProject::class,
            // Resolves the VerificationSession from a short-lived hosted session_token.
            'session.token' => \App\Http\Middleware\SessionTokenAuth::class,
            // Resolves the ConsoleUser from a Bearer console token (Valyd SSO session).
            'console.auth' => \App\Http\Middleware\ConsoleAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\InsufficientBalanceException $e, $request) {
            return \App\Helpers\GlobalHelper::apiError(
                'insufficient_balance',
                $e->getMessage(),
                402,
                ['required' => round($e->required, 4), 'available' => round($e->available, 4)],
            );
        });
    })->create();
