<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Shipped = 'SHIPPED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    /**
     * Status yang dihitung sebagai omzet (laporan & dashboard).
     *
     * @return list<self>
     */
    public static function revenueStatuses(): array
    {
        return [self::Paid, self::Shipped, self::Completed];
    }

    /**
     * @return list<string>
     */
    public static function revenueValues(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::revenueStatuses());
    }

    /**
     * Nama kolom timestamp yang diisi saat order masuk ke status ini.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Paid => 'paid_at',
            self::Shipped => 'shipped_at',
            self::Completed => 'completed_at',
            self::Cancelled => 'cancelled_at',
            self::Pending => null,
        };
    }
}
