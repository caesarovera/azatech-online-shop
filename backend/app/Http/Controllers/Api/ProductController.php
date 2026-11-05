<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Stock\CreateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Controller tipis: validasi (Form Request) → otorisasi (Policy) → panggil Action/Model → Resource.
 */
final class ProductController extends Controller
{
    public function index(IndexProductRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $products = Product::query()
            ->when($request->search(), function ($q, string $search): void {
                $q->where(function ($q) use ($search): void {
                    $q->where('product_name', 'ILIKE', "%{$search}%")
                        ->orWhere('product_code', 'ILIKE', "%{$search}%");
                });
            })
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('stock', '<', 'low_stock_threshold'))
            ->orderBy($request->sortColumn(), $request->sortDirection())
            ->orderBy('id') // tie-breaker supaya pagination stabil
            ->paginate($request->perPage());

        return ApiResponse::paginated($products, ProductResource::class);
    }

    /** Otorisasi: StoreProductRequest::authorize() (admin saja). */
    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->safe()->except('stock');
        $product = $action->execute($data, (int) $request->validated('stock', 0), $user);

        return ApiResponse::success(new ProductResource($product), 'Produk berhasil dibuat', 201);
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return ApiResponse::success(new ProductResource($product));
    }

    /** Otorisasi: UpdateProductRequest::authorize() (admin saja). */
    public function update(UpdateProductRequest $request, Product $product, DashboardService $dashboard): JsonResponse
    {
        $product->update($request->validated());
        $dashboard->forget();

        return ApiResponse::success(new ProductResource($product), 'Produk berhasil diperbarui');
    }

    public function destroy(Product $product, DashboardService $dashboard): JsonResponse
    {
        Gate::authorize('delete', $product);

        $product->delete(); // soft delete: order lama tetap bisa menampilkan produk ini
        $dashboard->forget();

        return response()->json(null, 204);
    }
}
