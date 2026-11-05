<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Jobs\NotifyLowStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->customer = Customer::factory()->create();
});

it('membuat order multi-item: harga dari server, stok berkurang, ledger tercatat', function () {
    $a = product(stock: 10, price: '12000.00');
    $b = product(stock: 5, price: '5500.50');

    $response = postOrder(cashier(), $this->customer, [
        ['product_id' => $a->id, 'quantity' => 2],
        ['product_id' => $b->id, 'quantity' => 1],
    ])->assertCreated();

    $response->assertJsonPath('data.status', 'PENDING')
        ->assertJsonPath('data.total_amount', '29500.50')
        ->assertJsonPath('data.items.0.unit_price', '12000.00')
        ->assertJsonPath('data.items.0.subtotal', '24000.00')
        ->assertJsonPath('data.status_histories.0.to_status', 'PENDING');

    expect($response->json('data.order_number'))->toMatch('/^INV-\d{8}-\d{4}$/')
        ->and($a->fresh()->stock)->toBe(8)
        ->and($b->fresh()->stock)->toBe(4);

    $movements = StockMovement::query()->where('type', StockMovementType::OrderOut)->orderBy('product_id')->get();
    expect($movements)->toHaveCount(2)
        ->and($movements[0]->quantity_change)->toBe(-2)
        ->and($movements[0]->stock_after)->toBe(8)
        ->and($movements[0]->reference_type)->toBe('order');
});

it('mengabaikan price/total dari client', function () {
    $a = product(stock: 10, price: '12000.00');

    postOrder(cashier(), $this->customer, [['product_id' => $a->id, 'quantity' => 1, 'unit_price' => 1]], extra: ['total_amount' => 1])
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '12000.00');
});

it('snapshot unit_price: perubahan harga produk tidak mengubah order lama', function () {
    $a = product(stock: 10, price: '12000.00');
    $orderId = postOrder(cashier(), $this->customer, [['product_id' => $a->id, 'quantity' => 1]])->json('data.id');

    $a->update(['price' => '99999.00']);

    expect(Order::find($orderId)->items()->first()->unit_price)->toBe('12000.00');
});

it('stok tidak cukup → 422 dengan error per item dan TIDAK ada data tersimpan (atomic)', function () {
    $a = product(stock: 10);
    $b = product(stock: 3, price: '5000.00');
    $b->update(['product_name' => 'Hydrococo 150ml']);

    postOrder(cashier(), $this->customer, [
        ['product_id' => $a->id, 'quantity' => 2],
        ['product_id' => $b->id, 'quantity' => 5],
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Stok tidak mencukupi')
        ->assertJsonPath('errors', ['items.1.quantity' => ['Stok Hydrococo 150ml tersisa 3, diminta 5']]);

    expect(Order::count())->toBe(0)
        ->and($a->fresh()->stock)->toBe(10) // item pertama ikut di-rollback
        ->and(StockMovement::count())->toBe(0);
});

it('validasi request order', function () {
    $deleted = product();
    $deleted->delete();

    $this->actingAs(cashier(), 'sanctum')
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/orders', ['customer_id' => 'bukan-uuid', 'items' => [['product_id' => $deleted->id, 'quantity' => 0]]])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['customer_id', 'items.0.product_id', 'items.0.quantity']]);
});

it('produk yang sama dua kali dalam satu order ditolak', function () {
    $a = product();

    postOrder(cashier(), $this->customer, [['product_id' => $a->id, 'quantity' => 1], ['product_id' => $a->id, 'quantity' => 1]])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['items.1.product_id']]);
});

it('nomor order berurutan per hari', function () {
    $a = product(stock: 10);
    $user = cashier();

    $first = postOrder($user, $this->customer, [['product_id' => $a->id, 'quantity' => 1]])->json('data.order_number');
    $second = postOrder($user, $this->customer, [['product_id' => $a->id, 'quantity' => 1]])->json('data.order_number');

    expect($first)->toBe('INV-'.now()->format('Ymd').'-0001')
        ->and($second)->toBe('INV-'.now()->format('Ymd').'-0002');
});

it('dispatch notifikasi stok menipis saat stok melewati threshold', function () {
    $a = product(stock: 6, threshold: 5);

    postOrder(cashier(), $this->customer, [['product_id' => $a->id, 'quantity' => 2]])->assertCreated();

    Queue::assertPushed(NotifyLowStock::class, fn (NotifyLowStock $job) => $job->productId === $a->id);
});

it('tidak dispatch notifikasi jika stok masih di atas threshold', function () {
    $a = product(stock: 50, threshold: 5);

    postOrder(cashier(), $this->customer, [['product_id' => $a->id, 'quantity' => 2]])->assertCreated();

    Queue::assertNotPushed(NotifyLowStock::class);
});

it('list order dengan filter status', function () {
    $a = product(stock: 10);
    $user = cashier();
    postOrder($user, $this->customer, [['product_id' => $a->id, 'quantity' => 1]]);
    Order::factory()->create(['status' => 'PAID']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/orders?status=PENDING')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'PENDING')
        ->assertJsonPath('data.0.items_count', 1);
});

it('tidak dispatch notifikasi lagi jika stok sudah di bawah threshold sebelum order', function () {
    $a = product(stock: 3, threshold: 5);

    postOrder(cashier(), $this->customer, [['product_id' => $a->id, 'quantity' => 1]])->assertCreated();

    Queue::assertNotPushed(NotifyLowStock::class);
});
