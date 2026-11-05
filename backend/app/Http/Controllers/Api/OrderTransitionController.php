<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Order\TransitionOrderAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Endpoint transisi status. Controller TIDAK berisi if-else status;
 * semua aturan ada di OrderStateMachine (dipanggil oleh TransitionOrderAction).
 */
final class OrderTransitionController extends Controller
{
    public function __construct(private readonly TransitionOrderAction $action) {}

    public function pay(Request $request, Order $order): JsonResponse
    {
        return $this->transition($request, $order, OrderStatus::Paid, 'Order ditandai PAID');
    }

    public function ship(Request $request, Order $order): JsonResponse
    {
        return $this->transition($request, $order, OrderStatus::Shipped, 'Order dikirim');
    }

    public function complete(Request $request, Order $order): JsonResponse
    {
        return $this->transition($request, $order, OrderStatus::Completed, 'Order selesai');
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        return $this->transition(
            $request,
            $order,
            OrderStatus::Cancelled,
            'Order dibatalkan',
            (string) $request->validated('cancel_reason'),
        );
    }

    private function transition(Request $request, Order $order, OrderStatus $to, string $message, ?string $reason = null): JsonResponse
    {
        Gate::authorize('transition', [$order, $to]);

        /** @var User $user */
        $user = $request->user();
        $updated = $this->action->execute($order, $to, $user, $reason);

        return ApiResponse::success(new OrderResource($updated), $message);
    }
}
