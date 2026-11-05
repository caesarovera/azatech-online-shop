<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;

final class InvalidOrderTransitionException extends DomainException
{
    public function __construct(public readonly OrderStatus $from, public readonly OrderStatus $to)
    {
        parent::__construct("Order berstatus {$from->value} tidak dapat diubah menjadi {$to->value}.");
    }

    public function status(): int
    {
        return 409;
    }
}
