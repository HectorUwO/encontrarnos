<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if ($status < 400 || $status >= 600 || $request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            if ($status >= 500 && config('app.debug')) {
                return $response;
            }

            if ($request->header('X-Inertia')) {
                $page = config("errors.pages.$status", config('errors.fallbacks.'.($status < 500 ? '4xx' : '5xx')));
                $errorResponse = Inertia::render('Error', [
                    'status' => $status,
                    'title' => $page['title'],
                    'description' => $page['description'],
                ])->toResponse($request)->setStatusCode($status);

                foreach (['Allow', 'Retry-After', 'WWW-Authenticate'] as $header) {
                    if ($response->headers->has($header)) {
                        $errorResponse->headers->set($header, $response->headers->all($header));
                    }
                }

                $response = $errorResponse;
            }

            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        });
    })->create();
