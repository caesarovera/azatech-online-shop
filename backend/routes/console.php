<?php

declare(strict_types=1);

use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\Schedule;

// Dijalankan oleh service "scheduler" (php artisan schedule:work) di docker-compose.

// Hapus idempotency key yang sudah lewat masa berlaku (24 jam).
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->hourly();

// Hapus token Sanctum yang sudah expired lebih dari 24 jam.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
