<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\DomainException;
use App\Http\Middleware\AssignRequestContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Request ID + access log untuk SEMUA request (global, paling luar).
        $middleware->prepend(AssignRequestContext::class);

        // API stateless dengan Bearer token: kalau tidak login, jangan redirect ke halaman login.
        $middleware->redirectGuestsTo(fn () => null);

        // Percayai proxy di depan container (nginx/load balancer) agar $request->ip() benar.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error bisnis (stok kurang, transisi invalid) bukan bug → tidak perlu di-report sebagai error.
        $exceptions->dontReport([DomainException::class]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Semua error di /api/* dirender ke format standar { success, message, errors }.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(ApiExceptionRenderer::class)->render($e);
        });
    })->create();
