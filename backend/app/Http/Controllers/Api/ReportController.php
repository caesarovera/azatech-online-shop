<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    /** Otorisasi: ReportRequest::authorize() (Gate view-reports, admin saja). */
    public function sales(ReportRequest $request): JsonResponse
    {
        return ApiResponse::success($this->reports->sales(
            $request->from(),
            $request->to(),
            (string) $request->validated('group_by', 'day'),
        ));
    }

    public function topProducts(ReportRequest $request): JsonResponse
    {
        return ApiResponse::success($this->reports->topProducts(
            $request->from(),
            $request->to(),
            (int) $request->validated('limit', 10),
        ));
    }
}
