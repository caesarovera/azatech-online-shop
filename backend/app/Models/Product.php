<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $product_code
 * @property string $product_name
 * @property int $stock
 * @property string $price
 * @property int $low_stock_threshold
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * "stock" sengaja TIDAK fillable: stok hanya boleh berubah lewat StockService
     * (order, cancel, adjustment) supaya selalu tercatat di ledger.
     *
     * @var list<string>
     */
    protected $fillable = ['product_code', 'product_name', 'price', 'low_stock_threshold'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'price' => 'decimal:2',
            'low_stock_threshold' => 'integer',
        ];
    }

    /** @return HasMany<StockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock < $this->low_stock_threshold;
    }
}
