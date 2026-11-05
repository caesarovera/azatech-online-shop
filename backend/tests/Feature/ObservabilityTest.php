<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('health check memeriksa database dan redis', function () {
    $response = $this->getJson('/api/health');

    // Redis mungkin tidak tersedia di semua environment test; yang penting format & status konsisten.
    expect($response->status())->toBeIn([200, 503]);
    $response->assertJsonPath('data.checks.database', 'up')
        ->assertJsonStructure(['success', 'message', 'data' => ['status', 'checks' => ['database', 'redis']]]);
});

it('X-Request-Id dari client diteruskan ke response', function () {
    $this->getJson('/api/health', ['X-Request-Id' => 'req-12345678'])
        ->assertHeader('X-Request-Id', 'req-12345678');
});

it('X-Request-Id digenerate jika tidak dikirim / tidak valid', function () {
    $id = $this->getJson('/api/health', ['X-Request-Id' => "bad id\n"])->headers->get('X-Request-Id');

    expect($id)->toBeUuid();
});

it('error 500 tidak membocorkan stack trace saat APP_DEBUG=false', function () {
    config(['app.debug' => false]);
    Route::get('/api/_boom', fn () => throw new RuntimeException('rahasia database password'));

    $this->getJson('/api/_boom')
        ->assertStatus(500)
        ->assertExactJson(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
});

it('CORS hanya mengizinkan origin frontend yang terdaftar', function () {
    $this->options('/api/products', [], [
        'Origin' => 'http://localhost:5173',
        'Access-Control-Request-Method' => 'GET',
    ])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

    $response = $this->options('/api/products', [], [
        'Origin' => 'https://evil.example',
        'Access-Control-Request-Method' => 'GET',
    ]);
    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example');
});

it('pembuatan order di-rate-limit', function () {
    config(['app.order_rate_limit' => 2]);
    $user = User::factory()->cashier()->create();

    for ($i = 0; $i < 2; $i++) {
        $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/orders', [])->assertStatus(422);
    }

    $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/orders', [])->assertStatus(429);
});
