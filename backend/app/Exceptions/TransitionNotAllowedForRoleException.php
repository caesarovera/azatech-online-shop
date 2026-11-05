<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Enums\UserRole;

final class TransitionNotAllowedForRoleException extends DomainException
{
    public function __construct(OrderStatus $to, UserRole $role)
    {
        parent::__construct("Role {$role->value} tidak diizinkan mengubah order menjadi {$to->value}.");
    }

    public function status(): int
    {
        return 403;
    }
}
