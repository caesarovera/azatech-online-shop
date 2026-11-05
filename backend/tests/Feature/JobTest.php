<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Jobs\NotifyLowStock;
use App\Jobs\SendOrderPaidNotification;
use App\Mail\OrderPaidMail;
use App\Models\Customer;
use App\Models\Order;
use App\Notifications\LowStockNotification;
use App\Services\InvoiceRenderer;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
});

function createPaidOrder(?string $email = 'budi@example.com'): Order
{
    Queue::fake(); // jangan jalankan job saat setup
    $customer = Customer::factory()->create(['email' => $email]);
    $user = cashier();
    $id = postOrder($user, $customer, [['product_id' => product(stock: 50)->id, 'quantity' => 2]])->json('data.id');
    test()->actingAs($user, 'sanctum')->postJson("/api/orders/{$id}/pay")->assertOk();

    return Order::findOrFail($id);
}

it('job PAID membuat invoice HTML dan mengirim email ke customer', function () {
    $order = createPaidOrder();

    (new SendOrderPaidNotification($order->id))->handle(app(InvoiceRenderer::class));

    Storage::disk('local')->assertExists("invoices/{$order->order_number}.html");
    Mail::assertSent(OrderPaidMail::class, fn (OrderPaidMail $mail) => $mail->hasTo('budi@example.com'));
    expect($order->fresh()->invoice_sent_at)->not->toBeNull();
});

it('job aman dijalankan ulang: email tidak terkirim dua kali', function () {
    $order = createPaidOrder();
    $job = new SendOrderPaidNotification($order->id);

    $job->handle(app(InvoiceRenderer::class));
    $job->handle(app(InvoiceRenderer::class));

    Mail::assertSentCount(1);
});

it('job tetap membuat invoice walau customer tidak punya email', function () {
    $order = createPaidOrder(email: null);

    (new SendOrderPaidNotification($order->id))->handle(app(InvoiceRenderer::class));

    Mail::assertNothingSent();
    Storage::disk('local')->assertExists("invoices/{$order->order_number}.html");
});

it('job dikonfigurasi retry dengan backoff', function () {
    $job = new SendOrderPaidNotification(1);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([10, 30, 60, 120])
        ->and($job->middleware())->toHaveCount(1);
});

it('notifikasi stok menipis dikirim ke semua admin', function () {
    Notification::fake();
    $admins = collect([admin(), admin()]);
    cashier();
    $p = product(stock: 2, threshold: 5);

    (new NotifyLowStock($p->id))->handle();

    Notification::assertSentTo($admins, LowStockNotification::class);
    Notification::assertCount(2);
});

it('notifikasi stok tidak dikirim jika stok sudah kembali normal', function () {
    Notification::fake();
    admin();
    $p = product(stock: 100, threshold: 5);

    (new NotifyLowStock($p->id))->handle();

    Notification::assertNothingSent();
});

it('job notifikasi stok baru jalan setelah COMMIT — tidak jalan jika transaksi rollback', function () {
    // Queue "sync" (phpunit.xml) menghormati afterCommit: job dibuang jika transaksi rollback.
    Notification::fake();
    admin();
    $p = product(stock: 6, threshold: 5);
    $stock = app(StockService::class);

    try {
        DB::transaction(function () use ($p, $stock): void {
            $stock->move($stock->lockProducts([$p->id])->firstOrFail(), -2, StockMovementType::OrderOut);

            throw new RuntimeException('gagal setelah stok dikurangi');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    expect($p->fresh()->stock)->toBe(6);
    Notification::assertNothingSent();

    // Kontrol: transaksi yang sukses → notifikasi terkirim setelah commit.
    DB::transaction(fn () => $stock->move($stock->lockProducts([$p->id])->firstOrFail(), -2, StockMovementType::OrderOut));
    Notification::assertSentTimes(LowStockNotification::class, 1);
});
