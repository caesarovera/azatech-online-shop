<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Order\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/**
 * Policy menjawab "apakah role ini BOLEH melakukan aksi ini?" (→ 403).
 * State machine menjawab "apakah order DALAM STATUS INI bisa ditransisikan?" (→ 409).
 * Aturan role untuk transisi diambil dari tabel di OrderStateMachine agar tidak duplikat.
 */
final class OrderPolicy
{
    public function __construct(private readonly OrderStateMachine $stateMachine) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function transition(User $user, Order $order, OrderStatus $to): bool
    {
        return $this->stateMachine->roleMayTransitionTo($to, $user->role);
    }
}
