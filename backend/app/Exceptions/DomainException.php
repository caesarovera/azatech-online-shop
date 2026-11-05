<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base class untuk error bisnis. Setiap turunan menentukan HTTP status-nya sendiri,
 * lalu bootstrap/app.php merender semuanya ke format response standar.
 */
abstract class DomainException extends RuntimeException
{
    abstract public function status(): int;

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return [];
    }
}
