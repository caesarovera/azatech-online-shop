<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Semua agregasi dikerjakan oleh PostgreSQL (GROUP BY / SUM), bukan di PHP,
 * jadi hanya beberapa baris hasil yang dikirim ke aplikasi dan tidak ada N+1 query.
 *
 * Omzet dihitung berdasarkan paid_at (kapan uang diterima), hanya status PAID/SHIPPED/COMPLETED.
 * Index pendukung: orders(status, paid_at), order_items(order_id), order_items(product_id).
 */
final class ReportService
{
    /**
     * @return list<array{period: string, total_orders: int, revenue: string}>
     */
    public function sales(CarbonInterface $from, CarbonInterface $to, string $groupBy): array
    {
        $unit = $groupBy === 'month' ? 'month' : 'day';
        $format = $unit === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD';

        $rows = DB::table('orders')
            ->selectRaw("to_char(date_trunc('{$unit}', paid_at), '{$format}') AS period")
            ->selectRaw('COUNT(*) AS total_orders')
            ->selectRaw('SUM(total_amount) AS revenue')
            ->whereIn('status', OrderStatus::revenueValues())
            ->whereBetween('paid_at', [$from, $to])
            ->groupByRaw('1')
            ->orderByRaw('1')
            ->get();

        return $rows->map(fn (object $r): array => [
            'period' => (string) $r->period,
            'total_orders' => (int) $r->total_orders,
            'revenue' => number_format((float) $r->revenue, 2, '.', ''),
        ])->values()->all();
    }

    /**
     * @return list<array{product_id: int, product_code: string, product_name: string, total_quantity: int, revenue: string}>
     */
    public function topProducts(CarbonInterface $from, CarbonInterface $to, int $limit): array
    {
        $rows = DB::table('order_items AS oi')
            ->join('orders AS o', 'o.id', '=', 'oi.order_id')
            ->join('products AS p', 'p.id', '=', 'oi.product_id')
            ->select('p.id', 'p.product_code', 'p.product_name')
            ->selectRaw('SUM(oi.quantity) AS total_quantity')
            ->selectRaw('SUM(oi.subtotal) AS revenue')
            ->whereIn('o.status', OrderStatus::revenueValues())
            ->whereBetween('o.paid_at', [$from, $to])
            ->groupBy('p.id', 'p.product_code', 'p.product_name')
            ->orderByDesc('total_quantity')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $r): array => [
            'product_id' => (int) $r->id,
            'product_code' => (string) $r->product_code,
            'product_name' => (string) $r->product_name,
            'total_quantity' => (int) $r->total_quantity,
            'revenue' => number_format((float) $r->revenue, 2, '.', ''),
        ])->values()->all();
    }
}
