<?php

use App\Http\Middleware\EnsurePlanLimit;
use App\Http\Middleware\SetCurrentOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        // Bearer tokens (web SPA + both RN apps). Do not enable statefulApi():
        // that copies CSRF onto /api/* for SANCTUM_STATEFUL_DOMAINS and breaks
        // browser login (419) while Postman still works.
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
            'api/*',
        ]);
        $middleware->alias([
            'org' => SetCurrentOrganization::class,
            'plan' => EnsurePlanLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'code' => 'validation',
                ], 422);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getMessage() ?: 'Fehler';
                if ($message === 'This action is unauthorized.') {
                    $message = 'Keine Berechtigung für diese Aktion.';
                }

                return response()->json([
                    'message' => $message,
                    'errors' => [],
                    'code' => 'http',
                ], $e->getStatusCode());
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Keine Berechtigung für diese Aktion.',
                    'errors' => [],
                    'code' => 'http',
                ], 403);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Nicht angemeldet.',
                    'errors' => [],
                    'code' => 'http',
                ], 401);
            }
        });

        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => [],
                    'code' => 'domain',
                ], 422);
            }
        });
    })->create();
