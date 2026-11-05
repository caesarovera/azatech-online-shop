<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Ringkasan dashboard, di-cache di Redis.
 *
 * Strategi invalidasi: "cache-aside + explicit invalidation".
 *  - Dibaca: Cache::remember() → kalau tidak ada, hitung dari DB lalu simpan.
 *  - Ditulis: setiap aksi yang mengubah angka dashboard (order dibuat, status berubah,
 *    stok disesuaikan, produk/pelanggan berubah) memanggil forget() SETELAH commit.
 *  - TTL 5 menit sebagai jaring pengaman jika ada jalur yang lupa meng-invalidate
 *    (mis. angka "hari ini" yang berganti saat lewat tengah malam).
 */
final class DashboardService
{
    public const CACHE_KEY = 'dashboard:summary';

    public const TTL_SECONDS = 300;

    /**
     * @return array<string, int|string>
     */
    public function summary(): array
    {
        /** @var array<string, int|string> */
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, fn (): array => $this->compute());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, int|string>
     */
    private function compute(): array
    {
        $revenueStatuses = OrderStatus::revenueValues();
        $todayStart = now()->startOfDay();

        // Satu query agregat untuk angka order (FILTER clause PostgreSQL), bukan beberapa query terpisah.
        /** @var object{today_orders: int, today_revenue: string|null, pending_orders: int, revenue_30d: string|null} $orders */
        $orders = Order::query()
            ->toBase()
            ->selectRaw('COUNT(*) FILTER (WHERE order_date >= ?) AS today_orders', [$todayStart])
            ->selectRaw(
                'COALESCE(SUM(total_amount) FILTER (WHERE paid_at >= ? AND status IN (?, ?, ?)), 0) AS today_revenue',
                [$todayStart, ...$revenueStatuses],
            )
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS pending_orders', [OrderStatus::Pending->value])
            ->selectRaw(
                'COALESCE(SUM(total_amount) FILTER (WHERE paid_at >= ? AND status IN (?, ?, ?)), 0) AS revenue_30d',
                [now()->subDays(30)->startOfDay(), ...$revenueStatuses],
            )
            ->first();

        return [
            'today_orders' => (int) $orders->today_orders,
            'today_revenue' => number_format((float) $orders->today_revenue, 2, '.', ''),
            'pending_orders' => (int) $orders->pending_orders,
            'revenue_last_30_days' => number_format((float) $orders->revenue_30d, 2, '.', ''),
            'total_products' => Product::query()->count(),
            'low_stock_products' => Product::query()->whereColumn('stock', '<', 'low_stock_threshold')->count(),
            'total_customers' => Customer::query()->count(),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
