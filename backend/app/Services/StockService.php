<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InvalidStockAdjustmentException;
use App\Jobs\NotifyLowStock;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya pintu untuk mengubah kolom products.stock.
 *
 * Aturan:
 *  1. Selalu dipanggil DI DALAM DB::transaction().
 *  2. Produk harus di-lock dulu via lockProducts() (SELECT ... FOR UPDATE).
 *  3. Setiap perubahan stok = 1 baris di stock_movements (ledger).
 */
final class StockService
{
    /**
     * Mengunci baris produk dengan SELECT ... FOR UPDATE, diurutkan berdasarkan ID.
     *
     * Kenapa diurutkan? Mencegah DEADLOCK pada order multi-item:
     *   Tx A memesan [produk 1, produk 2], Tx B memesan [produk 2, produk 1].
     *   Tanpa urutan: A lock 1, B lock 2, A menunggu 2, B menunggu 1 → deadlock.
     *   Dengan urutan ID: keduanya lock 1 dulu → B menunggu A selesai → aman.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Product> keyed by product id
     */
    public function lockProducts(array $productIds, bool $withTrashed = false): Collection
    {
        $this->assertInTransaction();

        $ids = array_values(array_unique($productIds));
        sort($ids);

        $query = Product::query()
            ->whereIn('id', $ids)
            ->orderBy('id')      // urutan lock deterministik
            ->lockForUpdate();   // SELECT ... FOR UPDATE

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->get()->keyBy('id');
    }

    /**
     * Mengubah stok produk yang SUDAH di-lock dan mencatatnya ke ledger.
     *
     * @param  int  $change  bertanda: negatif = keluar, positif = masuk
     */
    public function move(
        Product $lockedProduct,
        int $change,
        StockMovementType $type,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $note = null,
        ?int $userId = null,
    ): StockMovement {
        $this->assertInTransaction();

        $before = $lockedProduct->stock;
        $after = $before + $change;

        if ($after < 0) {
            // Seharusnya sudah dicegah oleh pemanggil; ini guard terakhir sebelum CHECK constraint DB.
            throw new InvalidStockAdjustmentException($before, $change);
        }

        $lockedProduct->stock = $after;
        $lockedProduct->save();

        $movement = StockMovement::create([
            'product_id' => $lockedProduct->id,
            'type' => $type,
            'quantity_change' => $change,
            'stock_after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => $userId,
        ]);

        // Notifikasi hanya saat stok MELEWATI batas (dari >= threshold menjadi < threshold),
        // supaya admin tidak dibanjiri notifikasi setiap kali ada order kecil.
        $threshold = $lockedProduct->low_stock_threshold;
        if ($before >= $threshold && $after < $threshold) {
            // afterCommit: job baru dikirim ke queue setelah transaksi COMMIT.
            // Kalau transaksi rollback, notifikasi tidak pernah terkirim.
            NotifyLowStock::dispatch($lockedProduct->id)->afterCommit();
        }

        return $movement;
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('StockService harus dipanggil di dalam DB transaction.');
        }
    }
}
