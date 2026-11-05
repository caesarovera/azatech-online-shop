<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Order\CreateOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class OrderController extends Controller
{
    public function index(IndexOrderRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::query()
            ->with(['customer', 'creator'])   // eager load → 3 query total, bukan 1 + 2N
            ->withCount('items')
            ->when($request->validated('status'), fn ($q, string $status) => $q->where('status', $status))
            ->when($request->validated('customer_id'), fn ($q, string $id) => $q->where('customer_id', $id))
            ->when($request->validated('date_from'), fn ($q, string $d) => $q->where('order_date', '>=', $d))
            ->when($request->validated('date_to'), fn ($q, string $d) => $q->where('order_date', '<', now()->parse($d)->addDay()->startOfDay()))
            ->when($request->search(), fn ($q, string $s) => $q->where('order_number', 'ILIKE', "%{$s}%"))
            ->orderBy($request->sortColumn(), $request->sortDirection())
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return ApiResponse::paginated($orders, OrderResource::class);
    }

    /**
     * Buat order multi-item.
     *
     * Harga & total dihitung server. Idempotency ditangani middleware EnsureIdempotency:
     * key + payload sama → response yang sama di-replay (header Idempotent-Replayed: true);
     * key sama + payload berbeda → 422; request pertama masih diproses → 409.
     */
    #[HeaderParameter('Idempotency-Key', description: 'UUID unik per percobaan submit', required: true, format: 'uuid')]
    public function store(StoreOrderRequest $request, CreateOrderAction $action): JsonResponse
    {
        Gate::authorize('create', Order::class);

        /** @var User $user */
        $user = $request->user();

        /** @var list<array{product_id: int, quantity: int}> $items */
        $items = array_map(
            fn (array $i): array => ['product_id' => (int) $i['product_id'], 'quantity' => (int) $i['quantity']],
            $request->validated('items'),
        );

        $order = $action->execute($user, (string) $request->validated('customer_id'), $items);

        return ApiResponse::success(new OrderResource($order), 'Order berhasil dibuat', 201);
    }

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order->load(['items.product', 'customer', 'creator', 'statusHistories.changedBy']);

        return ApiResponse::success(new OrderResource($order));
    }
}
