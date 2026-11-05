<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Actions\Order\CreateOrderAction;
use App\Actions\Order\TransitionOrderAction;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class StockConcurrencyTest extends ConcurrencyTestCase
{
    public function test_20_order_paralel_untuk_stok_5_hanya_5_yang_sukses(): void
    {
        Queue::fake();
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock' => 5, 'low_stock_threshold' => 0]);

        $results = $this->runInParallel(20, function () use ($cashier, $customer, $product): string {
            try {
                app(CreateOrderAction::class)->execute(
                    User::findOrFail($cashier->id),
                    $customer->id,
                    [['product_id' => $product->id, 'quantity' => 1]],
                );

                return 'ok';
            } catch (DomainException $e) {
                return 'fail:'.$e->status();
            }
        });

        $counts = array_count_values($results);
        $this->assertSame(5, $counts['ok'] ?? 0, 'Hasil: '.json_encode($counts));
        $this->assertSame(15, $counts['fail:422'] ?? 0, 'Hasil: '.json_encode($counts));

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(5, Order::count());
        $this->assertSame(5, StockMovement::where('type', StockMovementType::OrderOut)->count());
        // Nomor order unik walau dibuat bersamaan.
        $this->assertSame(5, Order::query()->distinct()->count('order_number'));
    }

    public function test_order_multi_item_dengan_urutan_berbeda_tidak_deadlock(): void
    {
        Queue::fake();
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $a = Product::factory()->create(['stock' => 100, 'low_stock_threshold' => 0]);
        $b = Product::factory()->create(['stock' => 100, 'low_stock_threshold' => 0]);

        // Separuh worker memesan [A, B], separuh [B, A] → pola klasik deadlock jika lock tidak diurutkan.
        $results = $this->runInParallel(16, function (int $i) use ($cashier, $customer, $a, $b): string {
            $items = $i % 2 === 0
                ? [['product_id' => $a->id, 'quantity' => 1], ['product_id' => $b->id, 'quantity' => 1]]
                : [['product_id' => $b->id, 'quantity' => 1], ['product_id' => $a->id, 'quantity' => 1]];

            app(CreateOrderAction::class)->execute(User::findOrFail($cashier->id), $customer->id, $items);

            return 'ok';
        });

        $this->assertSame(array_fill(0, 16, 'ok'), $results);
        $this->assertSame(84, $a->fresh()->stock);
        $this->assertSame(84, $b->fresh()->stock);
    }

    public function test_cancel_paralel_hanya_mengembalikan_stok_sekali(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'low_stock_threshold' => 0]);
        $order = app(CreateOrderAction::class)->execute($admin, $customer->id, [['product_id' => $product->id, 'quantity' => 4]]);
        $this->assertSame(6, $product->fresh()->stock);

        $results = $this->runInParallel(10, function () use ($admin, $order): string {
            try {
                app(TransitionOrderAction::class)->execute(Order::findOrFail($order->id), OrderStatus::Cancelled, User::findOrFail($admin->id), 'dobel klik');

                return 'ok';
            } catch (DomainException $e) {
                return 'fail:'.$e->status();
            }
        });

        $counts = array_count_values($results);
        $this->assertSame(1, $counts['ok'] ?? 0, json_encode($counts));
        $this->assertSame(9, $counts['fail:409'] ?? 0, json_encode($counts));
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(1, StockMovement::where('type', StockMovementType::CancelRestore)->count());
    }

    public function test_request_paralel_dengan_idempotency_key_sama_hanya_membuat_satu_order(): void
    {
        Queue::fake();
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock' => 10, 'low_stock_threshold' => 0]);
        $key = (string) Str::uuid();

        $results = $this->runInParallel(8, function () use ($cashier, $customer, $product, $key): string {
            $response = $this->actingAs(User::findOrFail($cashier->id), 'sanctum')
                ->withHeader('Idempotency-Key', $key)
                ->postJson('/api/orders', ['customer_id' => $customer->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);

            return (string) $response->status();
        });

        // Yang menang: 201. Yang kalah saat pemenang masih proses: 409. Yang datang setelah selesai: 201 replay.
        foreach ($results as $status) {
            $this->assertContains($status, ['201', '409'], json_encode($results));
        }
        $this->assertSame(1, Order::count(), json_encode($results));
        $this->assertSame(9, $product->fresh()->stock);
    }
}
