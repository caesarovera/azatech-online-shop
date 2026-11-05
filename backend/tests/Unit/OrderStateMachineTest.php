<?php

declare(strict_types=1);

use App\Domain\Order\OrderStateMachine;
use App\Enums\OrderStatus as S;
use App\Enums\UserRole as R;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\TransitionNotAllowedForRoleException;

beforeEach(fn () => $this->machine = new OrderStateMachine);

it('mengizinkan transisi yang valid sesuai tabel', function (S $from, S $to, R $role) {
    $this->machine->assertTransition($from, $to, $role);
    expect($this->machine->canTransition($from, $to))->toBeTrue();
})->with([
    'pending → paid (cashier)' => [S::Pending, S::Paid, R::Cashier],
    'pending → paid (admin)' => [S::Pending, S::Paid, R::Admin],
    'paid → shipped (admin)' => [S::Paid, S::Shipped, R::Admin],
    'shipped → completed (admin)' => [S::Shipped, S::Completed, R::Admin],
    'pending → cancelled (admin)' => [S::Pending, S::Cancelled, R::Admin],
    'paid → cancelled (admin)' => [S::Paid, S::Cancelled, R::Admin],
]);

it('menolak transisi yang tidak valid dengan 409', function (S $from, S $to) {
    expect(fn () => $this->machine->assertTransition($from, $to, R::Admin))
        ->toThrow(InvalidOrderTransitionException::class);
})->with([
    'shipped → cancelled' => [S::Shipped, S::Cancelled],
    'completed → cancelled' => [S::Completed, S::Cancelled],
    'cancelled → cancelled' => [S::Cancelled, S::Cancelled],
    'pending → shipped (lompat)' => [S::Pending, S::Shipped],
    'paid → paid' => [S::Paid, S::Paid],
    'completed → pending' => [S::Completed, S::Pending],
    'cancelled → paid' => [S::Cancelled, S::Paid],
]);

it('menolak role yang tidak berhak dengan 403', function (S $from, S $to) {
    expect(fn () => $this->machine->assertTransition($from, $to, R::Cashier))
        ->toThrow(TransitionNotAllowedForRoleException::class);
})->with([
    [S::Paid, S::Shipped],
    [S::Shipped, S::Completed],
    [S::Pending, S::Cancelled],
]);

it('memeriksa status dulu baru role (transisi invalid tetap 409 walau role kasir)', function () {
    expect(fn () => $this->machine->assertTransition(S::Completed, S::Cancelled, R::Cashier))
        ->toThrow(InvalidOrderTransitionException::class);
});

it('mengembalikan aksi yang tersedia per status dan role', function () {
    expect($this->machine->availableTransitions(S::Pending, R::Admin))->toBe([S::Paid, S::Cancelled])
        ->and($this->machine->availableTransitions(S::Pending, R::Cashier))->toBe([S::Paid])
        ->and($this->machine->availableTransitions(S::Paid, R::Cashier))->toBe([])
        ->and($this->machine->availableTransitions(S::Completed, R::Admin))->toBe([]);
});

it('roleMayTransitionTo dipakai policy', function () {
    expect($this->machine->roleMayTransitionTo(S::Paid, R::Cashier))->toBeTrue()
        ->and($this->machine->roleMayTransitionTo(S::Shipped, R::Cashier))->toBeFalse()
        ->and($this->machine->roleMayTransitionTo(S::Cancelled, R::Admin))->toBeTrue();
});
