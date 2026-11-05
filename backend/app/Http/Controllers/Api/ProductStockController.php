<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Stock\AdjustStockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ProductStockController extends Controller
{
    /** Otorisasi: StockAdjustmentRequest::authorize() (admin saja). */
    public function adjust(StockAdjustmentRequest $request, Product $product, AdjustStockAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $movement = $action->execute(
            $product,
            (int) $request->validated('quantity_change'),
            (string) $request->validated('note'),
            $user,
        );

        return ApiResponse::success([
            'movement' => (new StockMovementResource($movement->load('creator')))->resolve($request),
            'product' => (new ProductResource($product->refresh()))->resolve($request),
        ], 'Stok berhasil disesuaikan', 201);
    }

    public function movements(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewStockMovements', $product);

        $movements = $product->stockMovements()
            ->with('creator')   // eager load → hindari N+1
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return ApiResponse::paginated($movements, StockMovementResource::class);
    }
}
