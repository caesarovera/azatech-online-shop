<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Publik (tanpa auth). Dipakai Docker healthcheck / load balancer.
 * 200 jika DB & Redis OK, 503 jika salah satu gagal. Detail error hanya ditulis ke log.
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('SELECT 1')),
            'redis' => $this->check(fn () => Redis::connection()->ping()),
        ];

        $healthy = ! in_array('down', $checks, true);

        return response()->json([
            'success' => $healthy,
            'message' => $healthy ? 'OK' : 'Service degraded',
            'data' => ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks, 'time' => now()->toIso8601String()],
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'up';
        } catch (Throwable $e) {
            Log::error('health_check_failed', ['error' => $e->getMessage()]);

            return 'down';
        }
    }
}
