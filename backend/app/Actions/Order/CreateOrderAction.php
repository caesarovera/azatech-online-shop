<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Domain\Order\OrderTotalCalculator;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\OrderNumberGenerator;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Use case: kasir/admin membuat order multi-item.
 *
 * Semua langkah di bawah berjalan dalam SATU database transaction:
 *   1. Gabungkan item dengan product_id yang sama.
 *   2. Lock semua produk (SELECT ... FOR UPDATE, urut ID).
 *   3. Validasi stok SEMUA item (kumpulkan semua error, bukan berhenti di error pertama).
 *   4. Hitung harga & total di server dari harga produk saat ini (snapshot unit_price).
 *   5. Generate order_number unik.
 *   6. Simpan order + item, kurangi stok (reservasi), tulis ledger ORDER_OUT.
 * Jika satu langkah gagal → ROLLBACK → tidak ada data setengah jadi.
 */
final class CreateOrderAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly OrderTotalCalculator $calculator,
        private readonly OrderNumberGenerator $numbers,
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     *
     * @throws InsufficientStockException
     */
    public function execute(User $user, string $customerId, array $items): Order
    {
        // 1. Gabungkan item duplikat, tapi ingat index pertamanya untuk pesan error per item.
        /** @var array<int, int> $quantities product_id => total quantity */
        $quantities = [];
        /** @var array<int, int> $firstIndex product_id => index item di request */
        $firstIndex = [];
        foreach ($items as $index => $item) {
            $productId = (int) $item['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $item['quantity'];
            $firstIndex[$productId] ??= $index;
        }

        $order = DB::transaction(function () use ($user, $customerId, $quantities, $firstIndex): Order {
            // 2. Lock produk. Request lain untuk produk yang sama akan MENUNGGU di sini.
            $products = $this->stock->lockProducts(array_keys($quantities));

            // 3. Validasi stok dengan data yang sudah ter-lock (tidak mungkin berubah sampai commit).
            $errors = [];
            foreach ($quantities as $productId => $quantity) {
                $index = $firstIndex[$productId];
                $product = $products->get($productId);

                if ($product === null) {
                    $errors["items.{$index}.product_id"] = ['Produk tidak ditemukan atau sudah dihapus.'];

                    continue;
                }

                if ($product->stock < $quantity) {
                    $errors["items.{$index}.quantity"] = [
                        "Stok {$product->product_name} tersisa {$product->stock}, diminta {$quantity}",
                    ];
                }
            }

            if ($errors !== []) {
                throw new InsufficientStockException($errors);
            }

            // 4. Harga diambil dari DB, BUKAN dari client.
            $lines = [];
            foreach ($quantities as $productId => $quantity) {
                $lines[] = ['unit_price' => (string) $products[$productId]->price, 'quantity' => $quantity];
            }
            $totals = $this->calculator->calculate($lines);

            // 5 & 6. Simpan order.
            $now = now();
            $order = new Order;
            $order->order_number = $this->numbers->next($now);
            $order->customer_id = $customerId;
            $order->created_by = $user->id;
            $order->status = OrderStatus::Pending;
            $order->total_amount = $totals['total'];
            $order->order_date = $now;
            $order->save();

            $lineIndex = 0;
            foreach ($quantities as $productId => $quantity) {
                $line = $totals['lines'][$lineIndex++];

                $order->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                ]);

                $this->stock->move(
                    lockedProduct: $products[$productId],
                    change: -$quantity,
                    type: StockMovementType::OrderOut,
                    referenceType: 'order',
                    referenceId: (string) $order->id,
                    note: "Order {$order->order_number}",
                    userId: $user->id,
                );
            }

            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => OrderStatus::Pending->value,
                'changed_by' => $user->id,
            ]);

            return $order;
        });

        $this->dashboard->forget();

        return $order->load(['items.product', 'customer', 'creator', 'statusHistories.changedBy']);
    }
}
