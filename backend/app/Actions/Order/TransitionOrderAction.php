<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Domain\Order\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Jobs\SendOrderPaidNotification;
use App\Models\Order;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Use case: mengubah status order (pay / ship / complete / cancel).
 *
 * Kunci keamanan terhadap request ganda: baris order di-lock (SELECT ... FOR UPDATE)
 * lalu status DIBACA ULANG dari DB di dalam transaksi. Jika dua request cancel datang
 * bersamaan, request kedua menunggu lock, lalu melihat status sudah CANCELLED
 * → state machine menolak (409) → stok tidak dikembalikan dua kali.
 */
final class TransitionOrderAction
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly StockService $stock,
        private readonly DashboardService $dashboard,
    ) {}

    public function execute(Order $order, OrderStatus $to, User $user, ?string $reason = null): Order
    {
        $updated = DB::transaction(function () use ($order, $to, $user, $reason): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            // Lempar 409 (transisi tidak valid) atau 403 (role tidak boleh).
            $this->stateMachine->assertTransition($from, $to, $user->role);

            $locked->status = $to;
            $timestampColumn = $to->timestampColumn();
            if ($timestampColumn !== null) {
                $locked->{$timestampColumn} = now();
            }

            if ($to === OrderStatus::Cancelled) {
                $locked->cancel_reason = $reason;
                $this->restoreStock($locked, $user);
            }

            $locked->save();

            $locked->statusHistories()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => $reason,
                'changed_by' => $user->id,
            ]);

            if ($to === OrderStatus::Paid) {
                // Invoice + email dikerjakan worker, bukan di request HTTP.
                SendOrderPaidNotification::dispatch($locked->id)->afterCommit();
            }

            return $locked;
        });

        $this->dashboard->forget();

        return $updated->load(['items.product', 'customer', 'creator', 'statusHistories.changedBy']);
    }

    /**
     * Kembalikan stok semua item + catat CANCEL_RESTORE di ledger.
     * Urutan lock: order dulu (di atas), lalu produk urut ID → konsisten dengan alur lain.
     */
    private function restoreStock(Order $order, User $user): void
    {
        $items = $order->items()->get();
        $products = $this->stock->lockProducts($items->pluck('product_id')->all(), withTrashed: true);

        foreach ($items->sortBy('product_id') as $item) {
            $this->stock->move(
                lockedProduct: $products[$item->product_id],
                change: $item->quantity,
                type: StockMovementType::CancelRestore,
                referenceType: 'order',
                referenceId: (string) $order->id,
                note: "Cancel {$order->order_number}",
                userId: $user->id,
            );
        }
    }
}
