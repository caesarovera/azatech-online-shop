<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceRenderer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class OrderInvoiceController extends Controller
{
    public function show(Order $order, InvoiceRenderer $invoices): Response|JsonResponse
    {
        Gate::authorize('view', $order);

        if (! in_array($order->status, OrderStatus::revenueStatuses(), true)) {
            return ApiResponse::error('Invoice hanya tersedia untuk order yang sudah dibayar.', 409);
        }

        // Pakai file hasil job jika sudah ada; kalau job belum jalan, render on-the-fly.
        $path = $invoices->path($order);
        $html = Storage::disk('local')->exists($path)
            ? (string) Storage::disk('local')->get($path)
            : $invoices->render($order);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"invoice-{$order->order_number}.html\"",
        ]);
    }
}
