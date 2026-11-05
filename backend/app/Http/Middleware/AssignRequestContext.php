<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Observability:
 *  - Ambil X-Request-Id dari client (jika valid) atau generate UUID baru.
 *  - Simpan di Context Laravel → otomatis ikut di SETIAP baris log, dan ikut terbawa
 *    ke queued job (job yang di-dispatch dari request ini punya request_id yang sama).
 *  - Kembalikan di response header.
 *  - Tulis 1 access log terstruktur per request (method, path, status, durasi).
 */
final class AssignRequestContext
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header(self::HEADER, '');
        // Validasi format supaya client tidak bisa menyuntikkan string aneh ke log.
        $requestId = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();

        $request->headers->set(self::HEADER, $requestId);
        Context::add('request_id', $requestId);

        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(self::HEADER, $requestId);

        Log::info('http_request', [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 1),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
