<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Membuat produk baru. Stok awal dicatat sebagai ledger INITIAL,
 * sehingga SUM(quantity_change) di ledger selalu = products.stock.
 */
final class CreateProductAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * @param  array{product_code: string, product_name: string, price: string|float|int, low_stock_threshold?: int}  $attributes
     */
    public function execute(array $attributes, int $initialStock, ?User $user = null): Product
    {
        $product = DB::transaction(function () use ($attributes, $initialStock, $user): Product {
            $product = Product::create($attributes);

            if ($initialStock > 0) {
                $locked = $this->stock->lockProducts([$product->id])->firstOrFail();
                $this->stock->move(
                    lockedProduct: $locked,
                    change: $initialStock,
                    type: StockMovementType::Initial,
                    referenceType: 'product',
                    referenceId: (string) $product->id,
                    note: 'Stok awal',
                    userId: $user?->id,
                );
            }

            return $product->refresh();
        });

        $this->dashboard->forget();

        return $product;
    }
}
