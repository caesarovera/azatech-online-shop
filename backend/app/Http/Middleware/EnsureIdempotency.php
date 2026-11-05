<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk endpoint yang wajib idempotent (POST /api/orders).
 *
 * Alur:
 *   1. Validasi header Idempotency-Key (wajib UUID).
 *   2. begin(): klaim key / replay response lama / tolak (409 atau 422).
 *   3. Jalankan controller.
 *   4. Response 2xx → simpan (complete). Selain itu → lepas key (release).
 */
final class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';

    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header(self::HEADER, '');

        if (! Str::isUuid($key)) {
            return ApiResponse::error('Header Idempotency-Key wajib diisi dengan UUID.', 422, [
                self::HEADER => ['Header Idempotency-Key wajib berupa UUID.'],
            ]);
        }

        $userId = (int) $request->user()?->getAuthIdentifier();
        $key = strtolower($key);
        $hash = IdempotencyService::hashRequest($request->method(), $request->path(), $request->all());

        $stored = $this->idempotency->begin($userId, $key, $hash);

        if ($stored !== null) {
            return response()
                ->json($stored->response_body, (int) $stored->response_status)
                ->header('Idempotent-Replayed', 'true');
        }

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->idempotency->release($userId, $key);

            throw $e;
        }

        if ($response->isSuccessful()) {
            /** @var array<string, mixed> $body */
            $body = json_decode((string) $response->getContent(), true) ?? [];
            $this->idempotency->complete($userId, $key, $response->getStatusCode(), $body);
        } else {
            $this->idempotency->release($userId, $key);
        }

        return $response;
    }
}
