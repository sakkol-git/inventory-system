<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        // commands: __DIR__.'/../routes/console.php',
        // health: '/up',
        // apiPrefix: 'api/v1',
        // then: function () {
        //     Route::middleware('api')
        //         ->prefix('api/v1')
        //         ->group(app_path('Modules/Core/Routes/api.php'));

        //     Route::middleware('api')
        //         ->prefix('api/v1')
        //         ->group(app_path('Modules/Inventory/Routes/api.php'));

        //     Route::middleware('api')
        //         ->prefix('api/v1')
        //         ->group(app_path('Modules/Research/Routes/api.php'));

        //     Route::middleware('api')
        //         ->prefix('api/v1')
        //         ->group(app_path('Modules/Business/Routes/api.php'));
        // },
        web: __DIR__.'/../routes/web.php',
        api: [
            app_path('Modules/Core/Routes/api.php'),
            app_path('Modules/Inventory/Routes/api.php'),
            app_path('Modules/Research/Routes/api.php'),
            app_path('Modules/Business/Routes/api.php'),
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Modules\Core\Http\Middleware\ForceJsonResponse::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1',
        ]);

        $middleware->alias([
            'admin' => \App\Modules\Core\Http\Middleware\AdminMiddleware::class,
            'cache.api' => \App\Modules\Core\Http\Middleware\CacheApiResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ── Authentication ───────────────────────────────────────────────
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        // ── Validation ───────────────────────────────────────────────────
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // ── Model Not Found ──────────────────────────────────────────────
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                $model = class_basename($e->getModel());

                return response()->json([
                    'status' => 'error',
                    'message' => "{$model} not found.",
                ], 404);
            }
        });

        // ── Route Not Found ──────────────────────────────────────────────
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Endpoint not found.',
                ], 404);
            }
        });

        // ── Forbidden ────────────────────────────────────────────────────
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() ?: 'Forbidden.',
                ], 403);
            }
        });

        // ── Rate Limiting ────────────────────────────────────────────────
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Too many requests. Please try again later.',
                ], 429);
            }
        });

        // ── Generic Catch-All (API only) ─────────────────────────────────
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
                $payload = [
                    'status' => 'error',
                    'message' => $status === 500
                        ? 'An unexpected error occurred.'
                        : ($e->getMessage() ?: 'Error'),
                ];

                if (app()->hasDebugModeEnabled()) {
                    $payload['debug'] = [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ];
                }

                return response()->json($payload, $status);
            }
        });
    })->create();
