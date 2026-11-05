<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\StockMovement;

it('admin menyesuaikan stok dengan catatan → tercatat di ledger', function () {
    $product = product(stock: 10);

    $this->actingAs(admin(), 'sanctum')
        ->postJson("/api/products/{$product->id}/stock-adjustments", ['quantity_change' => 50, 'note' => 'Stock opname bulanan'])
        ->assertCreated()
        ->assertJsonPath('data.product.stock', 60)
        ->assertJsonPath('data.movement.type', 'ADJUSTMENT')
        ->assertJsonPath('data.movement.quantity_change', 50)
        ->assertJsonPath('data.movement.stock_after', 60);
});

it('penyesuaian yang membuat stok minus ditolak 422', function () {
    $product = product(stock: 3);

    $this->actingAs(admin(), 'sanctum')
        ->postJson("/api/products/{$product->id}/stock-adjustments", ['quantity_change' => -5, 'note' => 'Barang rusak'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['quantity_change']]);

    expect($product->fresh()->stock)->toBe(3);
});

it('catatan wajib dan perubahan tidak boleh 0', function () {
    $product = product();

    $this->actingAs(admin(), 'sanctum')
        ->postJson("/api/products/{$product->id}/stock-adjustments", ['quantity_change' => 0])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['quantity_change', 'note']]);
});

it('kasir tidak boleh stock adjustment atau melihat ledger', function () {
    $product = product();
    $user = cashier();

    $this->actingAs($user, 'sanctum')->postJson("/api/products/{$product->id}/stock-adjustments", ['quantity_change' => 1, 'note' => 'x x x'])->assertForbidden();
    $this->actingAs($user, 'sanctum')->getJson("/api/products/{$product->id}/stock-movements")->assertForbidden();
});

it('riwayat ledger per produk, terbaru di atas', function () {
    $product = product(stock: 0);
    $other = product(stock: 0);
    $admin = admin();

    foreach ([5, -2, 10] as $change) {
        $this->actingAs($admin, 'sanctum')->postJson("/api/products/{$product->id}/stock-adjustments", ['quantity_change' => $change, 'note' => 'opname']);
    }
    $this->actingAs($admin, 'sanctum')->postJson("/api/products/{$other->id}/stock-adjustments", ['quantity_change' => 1, 'note' => 'opname']);

    $this->actingAs($admin, 'sanctum')->getJson("/api/products/{$product->id}/stock-movements")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.quantity_change', 10)
        ->assertJsonPath('data.0.stock_after', 13)
        ->assertJsonPath('data.0.created_by', $admin->name);

    expect(StockMovement::query()->where('product_id', $product->id)->where('type', StockMovementType::Adjustment)->sum('quantity_change'))
        ->toBe(13);
});

it('kasir dengan payload invalid tetap dapat 403 (otorisasi sebelum validasi)', function () {
    $product = product();

    $this->actingAs(cashier(), 'sanctum')
        ->postJson("/api/products/{$product->id}/stock-adjustments", [])
        ->assertForbidden();
});
