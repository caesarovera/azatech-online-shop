<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InsufficientStockException extends DomainException
{
    /**
     * @param  array<string, list<string>>  $itemErrors  mis. ['items.1.quantity' => ['Stok ... tersisa 3, diminta 5']]
     */
    public function __construct(private readonly array $itemErrors)
    {
        parent::__construct('Stok tidak mencukupi');
    }

    public function status(): int
    {
        return 422;
    }

    public function errors(): array
    {
        return $this->itemErrors;
    }
}
