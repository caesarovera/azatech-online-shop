<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Jobs\SendOrderPaidNotification;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Queue::fake();
    $this->admin = admin();
    $this->cashier = cashier();
    $this->product = product(stock: 10);
    $id = postOrder($this->cashier, Customer::factory()->create(), [['product_id' => $this->product->id, 'quantity' => 3]])->json('data.id');
    $this->order = Order::findOrFail($id);
});

function transition(User $user, Order $order, string $action, array $body = []): TestResponse
{
    return test()->actingAs($user, 'sanctum')->postJson("/api/orders/{$order->id}/{$action}", $body);
}

it('kasir boleh menandai PAID → paid_at terisi, job invoice di-dispatch', function () {
    transition($this->cashier, $this->order, 'pay')
        ->assertOk()
        ->assertJsonPath('data.status', 'PAID');

    expect($this->order->fresh()->paid_at)->not->toBeNull();
    Queue::assertPushed(SendOrderPaidNotification::class, fn ($job) => $job->orderId === $this->order->id);
});

it('alur lengkap PENDING → PAID → SHIPPED → COMPLETED oleh admin dengan histori', function () {
    transition($this->admin, $this->order, 'pay')->assertOk();
    transition($this->admin, $this->order, 'ship')->assertOk()->assertJsonPath('data.status', 'SHIPPED');
    transition($this->admin, $this->order, 'complete')->assertOk()->assertJsonPath('data.status', 'COMPLETED')
        ->assertJsonCount(4, 'data.status_histories');

    $order = $this->order->fresh();
    expect($order->shipped_at)->not->toBeNull()->and($order->completed_at)->not->toBeNull();
});

it('kasir tidak boleh SHIP / COMPLETE / CANCEL (403)', function (string $action) {
    transition($this->admin, $this->order, 'pay');

    transition($this->cashier, $this->order, $action, ['cancel_reason' => 'test'])->assertForbidden();
})->with(['ship', 'complete', 'cancel']);

it('transisi tidak valid → 409', function () {
    transition($this->admin, $this->order, 'ship') // PENDING → SHIPPED (lompat)
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Order berstatus PENDING tidak dapat diubah menjadi SHIPPED.');
});

it('cancel wajib alasan', function () {
    transition($this->admin, $this->order, 'cancel')->assertStatus(422)->assertJsonStructure(['errors' => ['cancel_reason']]);
});

it('cancel mengembalikan stok + ledger CANCEL_RESTORE', function () {
    expect($this->product->fresh()->stock)->toBe(7);

    transition($this->admin, $this->order, 'cancel', ['cancel_reason' => 'Customer membatalkan'])
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED')
        ->assertJsonPath('data.cancel_reason', 'Customer membatalkan');

    expect($this->product->fresh()->stock)->toBe(10);
    $restore = StockMovement::query()->where('type', StockMovementType::CancelRestore)->sole();
    expect($restore->quantity_change)->toBe(3)->and($restore->stock_after)->toBe(10);
});

it('cancel dua kali → kedua ditolak 409 dan stok tidak bertambah dua kali', function () {
    transition($this->admin, $this->order, 'cancel', ['cancel_reason' => 'pertama'])->assertOk();
    transition($this->admin, $this->order, 'cancel', ['cancel_reason' => 'kedua'])->assertStatus(409);

    expect($this->product->fresh()->stock)->toBe(10)
        ->and(StockMovement::query()->where('type', StockMovementType::CancelRestore)->count())->toBe(1);
});

it('order SHIPPED tidak bisa dibatalkan', function () {
    transition($this->admin, $this->order, 'pay');
    transition($this->admin, $this->order, 'ship');

    transition($this->admin, $this->order, 'cancel', ['cancel_reason' => 'telat'])->assertStatus(409);
});

it('available_actions mengikuti status & role', function () {
    $this->actingAs($this->cashier, 'sanctum')->getJson("/api/orders/{$this->order->id}")
        ->assertJsonPath('available_actions', null) // bukan di root
        ->assertJsonPath('data.available_actions', ['PAID']);

    $this->actingAs($this->admin, 'sanctum')->getJson("/api/orders/{$this->order->id}")
        ->assertJsonPath('data.available_actions', ['PAID', 'CANCELLED']);
});

it('invoice hanya untuk order yang sudah dibayar', function () {
    $this->actingAs($this->cashier, 'sanctum')->get("/api/orders/{$this->order->id}/invoice")->assertStatus(409);

    transition($this->cashier, $this->order, 'pay');

    $this->actingAs($this->cashier, 'sanctum')->get("/api/orders/{$this->order->id}/invoice")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee($this->order->order_number);
});

it('kasir cancel tanpa alasan tetap 403, bukan 422', function () {
    transition($this->cashier, $this->order, 'cancel')->assertForbidden();
});
