<?php

declare(strict_types=1);

namespace App\Exceptions;

final class IdempotencyException extends DomainException
{
    private function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    /** Key sama dipakai untuk payload berbeda → 422 (request-nya sendiri yang salah). */
    public static function payloadMismatch(): self
    {
        return new self('Idempotency-Key sudah dipakai untuk payload yang berbeda.', 422);
    }

    /** Request pertama dengan key ini masih diproses → 409 (konflik sementara, boleh retry). */
    public static function stillProcessing(): self
    {
        return new self('Request dengan Idempotency-Key ini masih diproses. Coba lagi sebentar.', 409);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}
