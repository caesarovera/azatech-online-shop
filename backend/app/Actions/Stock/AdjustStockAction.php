<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Exceptions\InvalidStockAdjustmentException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Use case: admin melakukan stock opname / penyesuaian manual.
 */
final class AdjustStockAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DashboardService $dashboard,
    ) {}

    public function execute(Product $product, int $quantityChange, string $note, User $user): StockMovement
    {
        $movement = DB::transaction(function () use ($product, $quantityChange, $note, $user): StockMovement {
            $locked = $this->stock->lockProducts([$product->id])->get($product->id);
            if ($locked === null) {
                abort(404);
            }

            if ($locked->stock + $quantityChange < 0) {
                throw new InvalidStockAdjustmentException($locked->stock, $quantityChange);
            }

            return $this->stock->move(
                lockedProduct: $locked,
                change: $quantityChange,
                type: StockMovementType::Adjustment,
                referenceType: 'adjustment',
                note: $note,
                userId: $user->id,
            );
        });

        $this->dashboard->forget();

        return $movement;
    }
}
