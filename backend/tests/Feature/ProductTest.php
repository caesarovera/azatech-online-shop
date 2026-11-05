<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;

it('list produk dengan pagination meta standar', function () {
    Product::factory()->count(15)->create();

    $this->actingAs(cashier(), 'sanctum')
        ->getJson('/api/products?per_page=10&page=2')
        ->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 15)
        ->assertJsonPath('meta.total_pages', 2)
        ->assertJsonCount(5, 'data');
});

it('search dan sort produk', function () {
    Product::factory()->create(['product_code' => 'P1', 'product_name' => 'Hydrococo 150ml', 'price' => '12000.00']);
    Product::factory()->create(['product_code' => 'P2', 'product_name' => 'Hydrococo 1L', 'price' => '25000.00']);
    Product::factory()->create(['product_code' => 'P3', 'product_name' => 'Teh Botol', 'price' => '5000.00']);

    $this->actingAs(cashier(), 'sanctum')
        ->getJson('/api/products?search=hydro&sort=price&direction=desc')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.product_code', 'P2')
        ->assertJsonPath('data.1.product_code', 'P1');
});

it('menolak kolom sort yang tidak di-whitelist', function () {
    $this->actingAs(cashier(), 'sanctum')
        ->getJson('/api/products?sort=password')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['sort']]);
});

it('admin membuat produk + stok awal tercatat di ledger INITIAL', function () {
    $this->actingAs(admin(), 'sanctum')
        ->postJson('/api/products', [
            'product_code' => 'P0001', 'product_name' => 'Hydrococo 150ml', 'price' => 12000, 'stock' => 10, 'low_stock_threshold' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.stock', 10)
        ->assertJsonPath('data.price', '12000.00');

    $movement = StockMovement::sole();
    expect($movement->type)->toBe(StockMovementType::Initial)
        ->and($movement->quantity_change)->toBe(10)
        ->and($movement->stock_after)->toBe(10);
});

it('kasir tidak boleh mengelola produk (403)', function () {
    $user = cashier();
    $product = product();

    $this->actingAs($user, 'sanctum')->postJson('/api/products', ['product_code' => 'X1', 'product_name' => 'X', 'price' => 1000])->assertForbidden();
    $this->actingAs($user, 'sanctum')->putJson("/api/products/{$product->id}", ['product_name' => 'Y'])->assertForbidden();
    $this->actingAs($user, 'sanctum')->deleteJson("/api/products/{$product->id}")->assertForbidden();
});

it('validasi produk: kode unik, harga > 0', function () {
    product()->update(['product_code' => 'DUP']);

    $this->actingAs(admin(), 'sanctum')
        ->postJson('/api/products', ['product_code' => 'DUP', 'product_name' => 'A', 'price' => 0])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['product_code', 'price']]);
});

it('update produk tidak bisa mengubah stok (mass assignment terlindungi)', function () {
    $product = product(stock: 10);

    $this->actingAs(admin(), 'sanctum')
        ->putJson("/api/products/{$product->id}", ['product_name' => 'Nama baru', 'stock' => 9999])
        ->assertOk()
        ->assertJsonPath('data.product_name', 'Nama baru')
        ->assertJsonPath('data.stock', 10);
});

it('hapus produk = soft delete', function () {
    $product = product();

    $this->actingAs(admin(), 'sanctum')->deleteJson("/api/products/{$product->id}")->assertNoContent();

    $this->assertSoftDeleted($product);
    $this->actingAs(admin(), 'sanctum')->getJson("/api/products/{$product->id}")->assertNotFound()
        ->assertJsonPath('message', 'Data tidak ditemukan');
});
