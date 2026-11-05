<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IdempotencyException;
use App\Models\IdempotencyKey;
use Illuminate\Support\Carbon;

/**
 * Desain idempotency (lihat ADR-002 di ARCHITECTURE.md):
 *
 *  - Disimpan di PostgreSQL (tabel idempotency_keys), bukan Redis, supaya
 *    tahan restart dan berada di database yang sama dengan order.
 *  - Scope: (user_id, key). Masa berlaku: 24 jam.
 *  - Request pertama "mengklaim" key dengan INSERT ... ON CONFLICT DO NOTHING.
 *    Constraint UNIQUE menjamin hanya SATU request yang berhasil insert,
 *    walaupun 2 request dengan key sama datang di milidetik yang sama.
 *  - Status:
 *      processing → request pertama masih berjalan  → request lain dapat 409
 *      completed  → response tersimpan              → request ulang dapat replay
 *  - Response gagal (4xx/5xx) TIDAK disimpan: key dilepas supaya client bisa retry
 *    dengan key yang sama (mis. setelah stok ditambah).
 */
final class IdempotencyService
{
    public const TTL_HOURS = 24;

    /** Lebih besar dari batas waktu eksekusi request (max_execution_time) supaya tidak "diambil alih" saat masih jalan. */
    public const LOCK_SECONDS = 60;

    /**
     * @return IdempotencyKey|null null = request ini pemilik key dan harus diproses;
     *                             IdempotencyKey = response tersimpan yang harus di-replay
     *
     * @throws IdempotencyException
     */
    public function begin(int $userId, string $key, string $requestHash, int $attempt = 1): ?IdempotencyKey
    {
        $now = now();

        $inserted = IdempotencyKey::query()->insertOrIgnore([
            'user_id' => $userId,
            'key' => $key,
            'request_hash' => $requestHash,
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'locked_until' => $now->copy()->addSeconds(self::LOCK_SECONDS),
            'expires_at' => $now->copy()->addHours(self::TTL_HOURS),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 1) {
            return null; // kita pemiliknya
        }

        $existing = IdempotencyKey::query()->where('user_id', $userId)->where('key', $key)->first();

        // Baris bisa hilang di antara INSERT dan SELECT (request pertama gagal lalu release()). Coba lagi.
        if ($existing === null || $existing->expires_at->isPast()) {
            if ($existing !== null) {
                IdempotencyKey::query()->whereKey($existing->id)->where('expires_at', '<', $now)->delete();
            }

            if ($attempt >= 3) {
                throw IdempotencyException::stillProcessing();
            }

            return $this->begin($userId, $key, $requestHash, $attempt + 1);
        }

        if (! hash_equals($existing->request_hash, $requestHash)) {
            throw IdempotencyException::payloadMismatch();
        }

        if ($existing->status === IdempotencyKey::STATUS_COMPLETED) {
            return $existing;
        }

        // Masih processing. Jika lock sudah kedaluwarsa (proses sebelumnya crash), ambil alih secara atomik.
        if ($existing->locked_until !== null && $existing->locked_until->isPast()) {
            $takenOver = IdempotencyKey::query()
                ->whereKey($existing->id)
                ->where('status', IdempotencyKey::STATUS_PROCESSING)
                ->where('locked_until', '<', $now)
                ->update(['locked_until' => $now->copy()->addSeconds(self::LOCK_SECONDS), 'updated_at' => $now]);

            if ($takenOver === 1) {
                return null;
            }
        }

        throw IdempotencyException::stillProcessing();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function complete(int $userId, string $key, int $status, array $body): void
    {
        IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->update([
                'status' => IdempotencyKey::STATUS_COMPLETED,
                'response_status' => $status,
                'response_body' => json_encode($body, JSON_THROW_ON_ERROR),
                'locked_until' => null,
                'updated_at' => Carbon::now(),
            ]);
    }

    public function release(int $userId, string $key): void
    {
        IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->where('status', IdempotencyKey::STATUS_PROCESSING)
            ->delete();
    }

    /**
     * Hash payload yang dinormalisasi: urutan key JSON tidak mempengaruhi hasil,
     * sehingga {"a":1,"b":2} dan {"b":2,"a":1} dianggap payload yang sama.
     *
     * @param  array<mixed>  $payload
     */
    public static function hashRequest(string $method, string $path, array $payload): string
    {
        return hash('sha256', $method.'|'.$path.'|'.json_encode(self::sortRecursive($payload), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function sortRecursive(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::sortRecursive($v);
            }
        }

        if (! array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }
}
