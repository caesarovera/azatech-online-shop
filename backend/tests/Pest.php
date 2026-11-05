<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
| Feature test: RefreshDatabase → setiap test dibungkus transaksi lalu di-rollback (cepat & terisolasi).
| Concurrency test TIDAK memakai RefreshDatabase karena proses paralel harus melihat data yang sudah COMMIT
| (lihat tests/Concurrency/ConcurrencyTestCase.php).
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

function admin(): User
{
    return User::factory()->admin()->create();
}

function cashier(): User
{
    return User::factory()->cashier()->create();
}

/**
 * @param  list<array{product_id: int, quantity: int}>  $items
 * @param  array<string, mixed>  $extra
 */
function postOrder(User $user, Customer $customer, array $items, ?string $key = null, array $extra = []): TestResponse
{
    return test()->actingAs($user, 'sanctum')
        ->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())
        ->postJson('/api/orders', ['customer_id' => $customer->id, 'items' => $items, ...$extra]);
}

function product(int $stock = 10, string $price = '12000.00', int $threshold = 5): Product
{
    return Product::factory()->create(['stock' => $stock, 'price' => $price, 'low_stock_threshold' => $threshold]);
}
