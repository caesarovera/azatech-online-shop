<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris ledger stok (append-only).
 *
 * @property int $id
 * @property int $product_id
 * @property StockMovementType $type
 * @property int $quantity_change
 * @property int $stock_after
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null; // ledger tidak pernah di-update

    /** @var list<string> */
    protected $fillable = [
        'product_id', 'type', 'quantity_change', 'stock_after',
        'reference_type', 'reference_id', 'note', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity_change' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
