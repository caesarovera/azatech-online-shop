<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Notifikasi ke semua admin saat stok produk turun di bawah low_stock_threshold.
 * Disimpan di tabel notifications (channel database) + email.
 *
 * ShouldBeUnique: selama 10 menit hanya ada 1 job untuk produk yang sama di queue,
 * jadi admin tidak menerima notifikasi ganda.
 */
final class NotifyLowStock implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $productId) {}

    public function uniqueId(): string
    {
        return (string) $this->productId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $product = Product::withTrashed()->find($this->productId);

        // Bisa saja stok sudah ditambah lagi sebelum job jalan → tidak perlu notifikasi.
        if ($product === null || ! $product->isLowStock()) {
            return;
        }

        $admins = User::query()->where('role', UserRole::Admin)->get();
        Notification::send($admins, new LowStockNotification($product));

        Log::info('low_stock.notified', ['product_id' => $product->id, 'stock' => $product->stock]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('low_stock.failed_permanently', ['product_id' => $this->productId, 'error' => $e?->getMessage()]);
    }
}
