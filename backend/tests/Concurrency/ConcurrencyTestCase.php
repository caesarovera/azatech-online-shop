<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base class untuk test concurrency SUNGGUHAN:
 *  - Data setup di-COMMIT (bukan dibungkus transaksi seperti RefreshDatabase),
 *    karena proses lain hanya bisa melihat data yang sudah commit.
 *  - Request paralel dijalankan di proses terpisah (pcntl_fork), masing-masing
 *    dengan koneksi PostgreSQL sendiri → lock FOR UPDATE benar-benar diuji.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Ekstensi pcntl dibutuhkan untuk concurrency test.');
        }

        if (! RefreshDatabaseState::$migrated) {
            Artisan::call('migrate:fresh');
            RefreshDatabaseState::$migrated = true;
        }

        $this->truncateAll();
    }

    protected function tearDown(): void
    {
        $this->truncateAll();
        parent::tearDown();
    }

    /**
     * Jalankan $work di $count proses paralel. Setiap proses menunggu "aba-aba" yang sama
     * (barrier berbasis waktu) supaya benar-benar menyerang database bersamaan.
     *
     * @param  callable(int $workerIndex): string  $work  mengembalikan label hasil, mis. "ok" / "fail:409"
     * @return list<string>
     */
    protected function runInParallel(int $count, callable $work): array
    {
        $dir = sys_get_temp_dir().'/azatech-concurrency-'.uniqid();
        mkdir($dir);

        // Child TIDAK boleh memakai socket koneksi milik parent.
        DB::disconnect();

        $startAt = microtime(true) + 0.5;
        $pids = [];

        for ($i = 0; $i < $count; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('pcntl_fork gagal');
            }

            if ($pid === 0) {
                // === proses child ===
                DB::purge();
                app('auth')->forgetGuards();
                usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));

                try {
                    $result = $work($i);
                } catch (\Throwable $e) {
                    $result = 'exception:'.$e::class.':'.$e->getMessage();
                }

                file_put_contents("{$dir}/{$i}", $result);
                DB::disconnect();
                // SIGKILL diri sendiri: jangan jalankan shutdown handler PHPUnit di child.
                posix_kill(getmypid(), SIGKILL);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::purge();

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = is_file("{$dir}/{$i}") ? (string) file_get_contents("{$dir}/{$i}") : 'missing';
            @unlink("{$dir}/{$i}");
        }
        @rmdir($dir);

        return $results;
    }

    private function truncateAll(): void
    {
        $tables = collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'migrations'"))
            ->pluck('tablename')
            ->map(fn (string $t) => '"'.$t.'"')
            ->implode(', ');

        if ($tables !== '') {
            DB::statement("TRUNCATE {$tables} RESTART IDENTITY CASCADE");
        }
    }
}
