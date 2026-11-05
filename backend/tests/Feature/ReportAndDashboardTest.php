<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

function paidOrder(string $paidAt, string $total, string $status = 'PAID'): Order
{
    return Order::factory()->create([
        'status' => $status,
        'total_amount' => $total,
        'order_date' => $paidAt,
        'paid_at' => $paidAt,
    ]);
}

it('laporan sales per hari hanya menghitung PAID/SHIPPED/COMPLETED', function () {
    paidOrder('2026-10-01 10:00:00', '10000.00');
    paidOrder('2026-10-01 15:00:00', '5000.00', 'COMPLETED');
    paidOrder('2026-10-02 09:00:00', '7000.00', 'SHIPPED');
    Order::factory()->create(['status' => 'PENDING', 'order_date' => '2026-10-01 11:00:00', 'total_amount' => '99999.00']);
    Order::factory()->create(['status' => 'CANCELLED', 'order_date' => '2026-10-01 11:00:00', 'total_amount' => '99999.00']);

    $this->actingAs(admin(), 'sanctum')
        ->getJson('/api/reports/sales?from=2026-10-01&to=2026-10-31&group_by=day')
        ->assertOk()
        ->assertJsonPath('data', [
            ['period' => '2026-10-01', 'total_orders' => 2, 'revenue' => '15000.00'],
            ['period' => '2026-10-02', 'total_orders' => 1, 'revenue' => '7000.00'],
        ]);
});

it('laporan sales per bulan', function () {
    paidOrder('2026-09-15 10:00:00', '1000.00');
    paidOrder('2026-10-01 10:00:00', '2000.00');
    paidOrder('2026-10-20 10:00:00', '3000.00');

    $this->actingAs(admin(), 'sanctum')
        ->getJson('/api/reports/sales?from=2026-09-01&to=2026-10-31&group_by=month')
        ->assertOk()
        ->assertJsonPath('data.0', ['period' => '2026-09', 'total_orders' => 1, 'revenue' => '1000.00'])
        ->assertJsonPath('data.1', ['period' => '2026-10', 'total_orders' => 2, 'revenue' => '5000.00']);
});

it('top products berdasarkan quantity lalu omzet', function () {
    $a = product(stock: 100, price: '1000.00');
    $b = product(stock: 100, price: '50000.00');
    $customer = Customer::factory()->create();
    $admin = admin();
    Queue::fake();

    foreach ([[$a, 5], [$b, 2], [$a, 3]] as [$p, $qty]) {
        $id = postOrder($admin, $customer, [['product_id' => $p->id, 'quantity' => $qty]])->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$id}/pay")->assertOk();
    }
    // order PENDING tidak dihitung
    postOrder($admin, $customer, [['product_id' => $b->id, 'quantity' => 50]]);

    $today = now()->format('Y-m-d');
    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/reports/top-products?from={$today}&to={$today}&limit=5")
        ->assertOk()
        ->assertJsonPath('data.0.product_id', $a->id)
        ->assertJsonPath('data.0.total_quantity', 8)
        ->assertJsonPath('data.0.revenue', '8000.00')
        ->assertJsonPath('data.1.product_id', $b->id)
        ->assertJsonPath('data.1.total_quantity', 2);
});

it('kasir tidak boleh melihat laporan', function () {
    $this->actingAs(cashier(), 'sanctum')->getJson('/api/reports/sales?from=2026-10-01&to=2026-10-31')->assertForbidden();
    $this->actingAs(cashier(), 'sanctum')->getJson('/api/reports/top-products?from=2026-10-01&to=2026-10-31')->assertForbidden();
});

it('validasi parameter laporan', function () {
    $this->actingAs(admin(), 'sanctum')
        ->getJson('/api/reports/sales?from=2026-10-31&to=2026-10-01&group_by=week')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['to', 'group_by']]);
});

it('dashboard summary di-cache dan di-invalidate saat order dibuat', function () {
    Queue::fake();
    $user = cashier();
    $p = product(stock: 10);

    $this->actingAs($user, 'sanctum')->getJson('/api/dashboard/summary')->assertOk()->assertJsonPath('data.today_orders', 0);
    expect(Cache::has(DashboardService::CACHE_KEY))->toBeTrue();

    postOrder($user, Customer::factory()->create(), [['product_id' => $p->id, 'quantity' => 1]])->assertCreated();
    expect(Cache::has(DashboardService::CACHE_KEY))->toBeFalse();

    $this->actingAs($user, 'sanctum')->getJson('/api/dashboard/summary')
        ->assertJsonPath('data.today_orders', 1)
        ->assertJsonPath('data.pending_orders', 1);
});
