<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\TransitionNotAllowedForRoleException;

/**
 * State machine siklus status order.
 *
 * Satu-satunya sumber kebenaran untuk "dari status X boleh ke status Y oleh role apa".
 * Class ini murni (tanpa database, tanpa HTTP) sehingga mudah di-unit-test.
 *
 *   PENDING ──pay──▶ PAID ──ship──▶ SHIPPED ──complete──▶ COMPLETED
 *      │               │
 *      └────cancel─────┴──▶ CANCELLED
 */
final class OrderStateMachine
{
    /**
     * Tabel transisi: [from][to] => role yang diizinkan.
     *
     * @var array<string, array<string, list<UserRole>>>
     */
    private const array TRANSITIONS = [
        'PENDING' => [
            'PAID' => [UserRole::Admin, UserRole::Cashier],
            'CANCELLED' => [UserRole::Admin],
        ],
        'PAID' => [
            'SHIPPED' => [UserRole::Admin],
            'CANCELLED' => [UserRole::Admin],
        ],
        'SHIPPED' => [
            'COMPLETED' => [UserRole::Admin],
        ],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return array_key_exists($to->value, self::TRANSITIONS[$from->value]);
    }

    public function roleCanTransition(OrderStatus $from, OrderStatus $to, UserRole $role): bool
    {
        return in_array($role, self::TRANSITIONS[$from->value][$to->value] ?? [], true);
    }

    /**
     * Apakah role ini punya izin ke status tujuan dari status MANAPUN?
     * Dipakai oleh OrderPolicy (cek 403 sebelum menyentuh database).
     */
    public function roleMayTransitionTo(OrderStatus $to, UserRole $role): bool
    {
        foreach (self::TRANSITIONS as $targets) {
            if (in_array($role, $targets[$to->value] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validasi transisi. Urutan cek penting:
     *  1. transisi valid secara state?  (tidak → 409 Conflict)
     *  2. role boleh melakukannya?      (tidak → 403 Forbidden)
     *
     * @throws InvalidOrderTransitionException
     * @throws TransitionNotAllowedForRoleException
     */
    public function assertTransition(OrderStatus $from, OrderStatus $to, UserRole $role): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidOrderTransitionException($from, $to);
        }

        if (! $this->roleCanTransition($from, $to, $role)) {
            throw new TransitionNotAllowedForRoleException($to, $role);
        }
    }

    /**
     * Status tujuan yang tersedia untuk role tertentu (dipakai frontend untuk menampilkan tombol).
     *
     * @return list<OrderStatus>
     */
    public function availableTransitions(OrderStatus $from, UserRole $role): array
    {
        $result = [];
        foreach (self::TRANSITIONS[$from->value] as $to => $roles) {
            if (in_array($role, $roles, true)) {
                $result[] = OrderStatus::from($to);
            }
        }

        return $result;
    }
}
