<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('key');
            $table->string('request_hash', 64);       // sha256 dari method + path + body
            $table->string('status', 20);             // processing | completed
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('locked_until')->nullable(); // batas waktu "processing" dianggap macet
            $table->timestamp('expires_at');
            $table->timestamps();

            // Key di-scope per user: key yang sama dari user lain tidak bentrok.
            // UNIQUE inilah yang menjamin hanya 1 request "menang" saat request ganda datang bersamaan.
            $table->unique(['user_id', 'key']);
            $table->index('expires_at'); // untuk pruning
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
