<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Lihat produk: semua role. Kelola produk & stok: hanya admin.
 * Policy ini otomatis terhubung ke model Product (konvensi nama App\Policies\{Model}Policy).
 */
final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function adjustStock(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function viewStockMovements(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }
}
