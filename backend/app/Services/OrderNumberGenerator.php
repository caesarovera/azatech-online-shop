<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Menghasilkan nomor order unik format INV-YYYYMMDD-NNNN.
 *
 * Memakai counter harian di tabel order_number_sequences yang di-increment secara atomik
 * dengan UPSERT ... RETURNING. Dua request bersamaan tidak akan mendapat nomor yang sama
 * karena PostgreSQL mengunci baris counter sampai transaksi selesai.
 *
 * Alternatif yang ditolak:
 *  - SELECT MAX(order_number) + 1 → race condition (dua request baca MAX yang sama).
 *  - Random string → tidak berurutan, sulit dibaca kasir.
 */
final class OrderNumberGenerator
{
    public function next(CarbonInterface $date): string
    {
        /** @var object{last_number: int} $row */
        $row = DB::selectOne(
            'INSERT INTO order_number_sequences (sequence_date, last_number) VALUES (?, 1)
             ON CONFLICT (sequence_date)
             DO UPDATE SET last_number = order_number_sequences.last_number + 1
             RETURNING last_number',
            [$date->toDateString()],
        );

        return sprintf('INV-%s-%04d', $date->format('Ymd'), $row->last_number);
    }
}
