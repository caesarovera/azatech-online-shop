<?php

declare(strict_types=1);

use App\Models\Customer;

it('kasir boleh membuat dan mengubah pelanggan', function () {
    $user = cashier();

    $id = $this->actingAs($user, 'sanctum')
        ->postJson('/api/customers', ['customer_name' => 'Budi Santoso', 'gender' => 'male', 'birth_date' => '1995-02-10', 'phone' => '081234567890'])
        ->assertCreated()
        ->assertJsonPath('data.customer_name', 'Budi Santoso')
        ->json('data.id');

    expect($id)->toBeUuid();

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/customers/{$id}", ['customer_address' => 'Jakarta'])
        ->assertOk()
        ->assertJsonPath('data.customer_address', 'Jakarta');
});

it('kasir tidak boleh menghapus pelanggan, admin boleh (soft delete)', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(cashier(), 'sanctum')->deleteJson("/api/customers/{$customer->id}")->assertForbidden();
    $this->actingAs(admin(), 'sanctum')->deleteJson("/api/customers/{$customer->id}")->assertNoContent();

    $this->assertSoftDeleted($customer);
});

it('validasi pelanggan', function () {
    $this->actingAs(cashier(), 'sanctum')
        ->postJson('/api/customers', ['gender' => 'other', 'email' => 'bukan-email', 'birth_date' => '2999-01-01'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['customer_name', 'gender', 'email', 'birth_date']]);
});

it('search pelanggan', function () {
    // Data dibuat eksplisit: nama/email acak dari Faker bisa kebetulan mengandung "budi" (test flaky).
    Customer::factory()->create(['customer_name' => 'Budi Santoso', 'email' => 'santoso@example.com', 'phone' => '081111111111']);
    Customer::factory()->create(['customer_name' => 'Siti Aminah', 'email' => 'siti@example.com', 'phone' => '082222222222']);

    $this->actingAs(cashier(), 'sanctum')
        ->getJson('/api/customers?search=budi')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer_name', 'Budi Santoso');
});
