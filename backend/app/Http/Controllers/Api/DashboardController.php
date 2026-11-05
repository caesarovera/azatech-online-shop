<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DashboardController extends Controller
{
    public function summary(DashboardService $dashboard): JsonResponse
    {
        return ApiResponse::success($dashboard->summary());
    }
}
