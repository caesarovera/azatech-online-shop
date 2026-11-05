<?php

declare(strict_types=1);

use App\Domain\Order\OrderTotalCalculator;

it('menghitung subtotal dan total', function () {
    $result = (new OrderTotalCalculator)->calculate([
        ['unit_price' => '12000.00', 'quantity' => 2],
        ['unit_price' => '12000', 'quantity' => 1],
    ]);

    expect($result['lines'][0]['subtotal'])->toBe('24000.00')
        ->and($result['lines'][1]['unit_price'])->toBe('12000.00')
        ->and($result['total'])->toBe('36000.00');
});

it('tidak terkena error floating point', function () {
    // 0.1 + 0.2 di float = 0.30000000000000004
    $result = (new OrderTotalCalculator)->calculate([
        ['unit_price' => '0.10', 'quantity' => 1],
        ['unit_price' => '0.20', 'quantity' => 1],
        ['unit_price' => '19999.99', 'quantity' => 3],
    ]);

    expect($result['total'])->toBe('60000.27');
});

it('menolak quantity <= 0', function () {
    (new OrderTotalCalculator)->calculate([['unit_price' => '1000.00', 'quantity' => 0]]);
})->throws(InvalidArgumentException::class);

it('konversi sen dua arah', function (string $amount, int $cents) {
    expect(OrderTotalCalculator::toCents($amount))->toBe($cents)
        ->and(OrderTotalCalculator::fromCents($cents))->toBe(number_format($cents / 100, 2, '.', ''));
})->with([
    ['12000.00', 1_200_000],
    ['0.5', 50],
    ['5000.05', 500_005],
]);
