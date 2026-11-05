<?php

declare(strict_types=1);

namespace App\Domain\Order;

/**
 * Menghitung subtotal & total order di server.
 *
 * Uang dihitung dalam satuan sen (integer) untuk menghindari error floating point
 * (mis. 0.1 + 0.2 != 0.3), lalu dikembalikan sebagai string desimal 2 digit
 * yang cocok dengan kolom decimal(12,2)/decimal(14,2).
 */
final class OrderTotalCalculator
{
    /**
     * @param  list<array{unit_price: string, quantity: int}>  $lines
     * @return array{lines: list<array{unit_price: string, quantity: int, subtotal: string}>, total: string}
     */
    public function calculate(array $lines): array
    {
        $totalCents = 0;
        $result = [];

        foreach ($lines as $line) {
            if ($line['quantity'] <= 0) {
                throw new \InvalidArgumentException('Quantity harus lebih dari 0.');
            }

            $subtotalCents = self::toCents($line['unit_price']) * $line['quantity'];
            $totalCents += $subtotalCents;

            $result[] = [
                'unit_price' => self::fromCents(self::toCents($line['unit_price'])),
                'quantity' => $line['quantity'],
                'subtotal' => self::fromCents($subtotalCents),
            ];
        }

        return ['lines' => $result, 'total' => self::fromCents($totalCents)];
    }

    public static function toCents(string $amount): int
    {
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException("Format nominal tidak valid: {$amount}");
        }

        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-')), 2, '0');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }
}
