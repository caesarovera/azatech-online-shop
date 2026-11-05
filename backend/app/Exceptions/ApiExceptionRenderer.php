<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Memetakan exception → response JSON standar + HTTP status yang tepat.
 * Didaftarkan di bootstrap/app.php untuk semua route api/*.
 */
final class ApiExceptionRenderer
{
    public function render(Throwable $e): JsonResponse
    {
        $response = match (true) {
            $e instanceof ValidationException => ApiResponse::error('Validasi gagal', 422, $e->errors()),
            $e instanceof DomainException => ApiResponse::error($e->getMessage(), $e->status(), $e->errors()),
            $e instanceof AuthenticationException => ApiResponse::error('Tidak terautentikasi', 401),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiResponse::error('Anda tidak memiliki akses untuk aksi ini', 403),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::error('Data tidak ditemukan', 404),
            $e instanceof ThrottleRequestsException => ApiResponse::error('Terlalu banyak request, coba lagi nanti', 429),
            $e instanceof HttpExceptionInterface => ApiResponse::error($e->getMessage() !== '' ? $e->getMessage() : 'Request error', $e->getStatusCode()),
            default => $this->serverError($e),
        };

        if ($e instanceof HttpExceptionInterface) {
            // Pertahankan header seperti Retry-After dari rate limiter.
            $response->headers->add($e->getHeaders());
        }

        return $response;
    }

    private function serverError(Throwable $e): JsonResponse
    {
        // Di production (APP_DEBUG=false) stack trace TIDAK PERNAH dikirim ke client.
        if (! config('app.debug')) {
            return ApiResponse::error('Terjadi kesalahan pada server', 500);
        }

        return ApiResponse::error('Terjadi kesalahan pada server', 500, [
            'debug' => [$e::class.': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()],
        ]);
    }
}
