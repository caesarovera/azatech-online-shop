<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Services\IdempotencyService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    $this->user = cashier();
    $this->customer = Customer::factory()->create();
    $this->product = product(stock: 10);
    $this->items = [['product_id' => $this->product->id, 'quantity' => 2]];
});

it('request ulang dengan key & payload sama → response sama, order tidak dobel', function () {
    $key = (string) Str::uuid();

    $first = postOrder($this->user, $this->customer, $this->items, $key)->assertCreated();
    $second = postOrder($this->user, $this->customer, $this->items, $key)
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json())->toBe($first->json())
        ->and(Order::count())->toBe(1)
        ->and($this->product->fresh()->stock)->toBe(8);
});

it('urutan key JSON berbeda tetap dianggap payload sama', function () {
    $key = (string) Str::uuid();
    postOrder($this->user, $this->customer, $this->items, $key)->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/orders', ['items' => [['quantity' => 2, 'product_id' => $this->product->id]], 'customer_id' => $this->customer->id])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');
});

it('key sama dengan payload berbeda → 422', function () {
    $key = (string) Str::uuid();
    postOrder($this->user, $this->customer, $this->items, $key)->assertCreated();

    postOrder($this->user, $this->customer, [['product_id' => $this->product->id, 'quantity' => 5]], $key)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Idempotency-Key sudah dipakai untuk payload yang berbeda.');

    expect(Order::count())->toBe(1);
});

it('header Idempotency-Key wajib UUID', function (?string $key) {
    $request = $this->actingAs($this->user, 'sanctum');
    if ($key !== null) {
        $request = $request->withHeader('Idempotency-Key', $key);
    }

    $request->postJson('/api/orders', ['customer_id' => $this->customer->id, 'items' => $this->items])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['Idempotency-Key']]);
})->with([null, 'abc', '123']);

it('request pertama yang masih diproses → request kedua dapat 409', function () {
    $key = (string) Str::uuid();
    $payload = ['customer_id' => $this->customer->id, 'items' => $this->items];

    // Simulasikan request pertama sedang berjalan.
    IdempotencyKey::create([
        'user_id' => $this->user->id,
        'key' => $key,
        'request_hash' => IdempotencyService::hashRequest('POST', 'api/orders', $payload),
        'status' => IdempotencyKey::STATUS_PROCESSING,
        'locked_until' => now()->addMinute(),
        'expires_at' => now()->addDay(),
    ]);

    postOrder($this->user, $this->customer, $this->items, $key)->assertStatus(409);
    expect(Order::count())->toBe(0);
});

it('request gagal (stok kurang) melepas key sehingga bisa di-retry dengan key yang sama', function () {
    $key = (string) Str::uuid();
    $items = [['product_id' => $this->product->id, 'quantity' => 20]];

    postOrder($this->user, $this->customer, $items, $key)->assertStatus(422);
    expect(IdempotencyKey::count())->toBe(0);

    // Stok ditambah admin, kasir retry dengan key yang sama → berhasil.
    $this->product->forceFill(['stock' => 50])->save();
    postOrder($this->user, $this->customer, $items, $key)->assertCreated();
});

it('key di-scope per user', function () {
    $key = (string) Str::uuid();

    postOrder($this->user, $this->customer, $this->items, $key)->assertCreated();
    postOrder(cashier(), $this->customer, $this->items, $key)->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

    expect(Order::count())->toBe(2);
});

it('key yang sudah kedaluwarsa bisa dipakai lagi', function () {
    $key = (string) Str::uuid();
    postOrder($this->user, $this->customer, $this->items, $key)->assertCreated();

    $this->travel(IdempotencyService::TTL_HOURS + 1)->hours();

    postOrder($this->user, $this->customer, $this->items, $key)->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
    expect(Order::count())->toBe(2);
});
