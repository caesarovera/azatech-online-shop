<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Order\CreateOrderAction;
use App\Actions\Order\TransitionOrderAction;
use App\Actions\Stock\CreateProductAction;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder memakai Action yang sama dengan API (bukan insert langsung), sehingga data seed
 * konsisten: ledger stok, nomor order, histori status, dan timestamp transisi semuanya valid.
 *
 *  - 1 admin, 1 cashier
 *  - 50 produk (beberapa sengaja stok rendah)
 *  - 30 customer
 *  - 150 order tersebar di 90 hari terakhir dengan berbagai status
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command->warn('Database sudah berisi data, seeder dilewati. Pakai migrate:fresh --seed untuk reset.');

            return;
        }

        // Jangan kirim 100+ email/job ke queue saat seeding.
        Config::set('queue.connections.null', ['driver' => 'null']);
        Config::set('queue.default', 'null');

        fake()->seed(20261104); // data deterministik → mudah didemokan

        $admin = $this->createUser('Admin Azatech', 'admin@azatech.id', UserRole::Admin);
        $cashier = $this->createUser('Kasir Azatech', 'cashier@azatech.id', UserRole::Cashier);

        $this->seedProducts($admin);
        Customer::factory()->count(30)->create();

        $this->seedOrders($admin, $cashier);

        $this->command->info('Seed selesai: admin@azatech.id / password, cashier@azatech.id / password');
    }

    private function createUser(string $name, string $email, UserRole $role): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => Hash::make('password')]);
        $user->role = $role; // role tidak fillable → di-set eksplisit
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function seedProducts(User $admin): void
    {
        $createProduct = app(CreateProductAction::class);

        $createProduct->execute(
            ['product_code' => 'P0001', 'product_name' => 'Hydrococo 150ml', 'price' => '12000.00', 'low_stock_threshold' => 5],
            300,
            $admin,
        );

        for ($i = 2; $i <= 50; $i++) {
            $attributes = Product::factory()->raw(['product_code' => sprintf('P%04d', $i)]);
            // 5 produk terakhir sengaja stoknya kecil untuk demo notifikasi stok menipis.
            $stock = $i > 45 ? fake()->numberBetween(6, 12) : fake()->numberBetween(150, 400);
            unset($attributes['stock']);

            $createProduct->execute($attributes, $stock, $admin);
        }
    }

    private function seedOrders(User $admin, User $cashier): void
    {
        $create = app(CreateOrderAction::class);
        $transition = app(TransitionOrderAction::class);

        $customers = Customer::query()->pluck('id')->all();
        $products = Product::query()->where('product_code', '<=', 'P0045')->pluck('id')->all();

        $realNow = now();

        // 150 waktu acak dalam 90 hari terakhir, diurutkan supaya nomor order harian berurutan.
        $dates = collect(range(1, 150))
            ->map(fn () => now()->subMinutes(fake()->numberBetween(60, 90 * 24 * 60)))
            ->sort()
            ->values();

        foreach ($dates as $date) {
            Carbon::setTestNow($date);

            $items = collect(fake()->randomElements($products, fake()->numberBetween(1, 4)))
                ->map(fn (int $id) => ['product_id' => $id, 'quantity' => fake()->numberBetween(1, 5)])
                ->values()
                ->all();

            $creator = fake()->boolean(70) ? $cashier : $admin;
            $order = $create->execute($creator, fake()->randomElement($customers), $items);

            // Distribusi status: 10% PENDING, 10% CANCELLED, 20% PAID, 20% SHIPPED, 40% COMPLETED
            $roll = fake()->numberBetween(1, 100);
            $target = match (true) {
                $roll <= 10 => OrderStatus::Pending,
                $roll <= 20 => OrderStatus::Cancelled,
                $roll <= 40 => OrderStatus::Paid,
                $roll <= 60 => OrderStatus::Shipped,
                default => OrderStatus::Completed,
            };

            $path = match ($target) {
                OrderStatus::Pending => [],
                OrderStatus::Cancelled => [OrderStatus::Cancelled],
                OrderStatus::Paid => [OrderStatus::Paid],
                OrderStatus::Shipped => [OrderStatus::Paid, OrderStatus::Shipped],
                OrderStatus::Completed => [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Completed],
            };

            $at = $date->copy();
            foreach ($path as $step) {
                $at = $at->copy()->addMinutes(fake()->numberBetween(10, 600));
                if ($at->isAfter($realNow)) {
                    break;
                }
                Carbon::setTestNow($at);
                $actor = $step === OrderStatus::Paid ? $creator : $admin;
                $reason = $step === OrderStatus::Cancelled ? 'Customer membatalkan' : null;
                $order = $transition->execute($order, $step, $actor, $reason);
            }
        }

        Carbon::setTestNow();
    }
}
