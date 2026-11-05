<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidStockAdjustmentException extends DomainException
{
    public function __construct(int $current, int $change)
    {
        parent::__construct("Penyesuaian {$change} akan membuat stok negatif (stok saat ini {$current}).");
    }

    public function status(): int
    {
        return 422;
    }

    public function errors(): array
    {
        return ['quantity_change' => [$this->getMessage()]];
    }
}
