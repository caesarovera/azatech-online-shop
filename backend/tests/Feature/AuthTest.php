<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('login berhasil mengembalikan token dengan expiry', function () {
    $user = admin();

    $response = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.role', 'admin')
        ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['id', 'name', 'email', 'role']]]);

    $token = PersonalAccessToken::findToken($response->json('data.token'));
    expect($token)->not->toBeNull()
        ->and($token->expires_at)->not->toBeNull();
});

it('login gagal dengan password salah → 401 tanpa membocorkan detail', function () {
    $user = cashier();

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'salah'])
        ->assertStatus(401)
        ->assertExactJson(['success' => false, 'message' => 'Email atau password salah']);
});

it('validasi input login → 422 format standar', function () {
    $this->postJson('/api/auth/login', ['email' => 'bukan-email'])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['email', 'password']]);
});

it('/me mengembalikan user yang login', function () {
    $user = cashier();
    $token = $user->createToken('t', ['cashier'], now()->addHour())->plainTextToken;

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'cashier');
});

it('logout me-revoke token sehingga tidak bisa dipakai lagi', function () {
    $user = cashier();
    $token = $user->createToken('t', ['cashier'], now()->addHour())->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
    expect(PersonalAccessToken::count())->toBe(0);

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);
});

it('token yang sudah expired ditolak', function () {
    $user = cashier();
    $token = $user->createToken('t', ['cashier'], now()->subMinute())->plainTextToken;

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('message', 'Tidak terautentikasi');
});

it('endpoint terproteksi tanpa token → 401', function () {
    $this->getJson('/api/auth/me')->assertStatus(401);
});

it('login di-rate-limit setelah 5 percobaan per menit', function () {
    User::factory()->create(['email' => 'x@azatech.id']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', ['email' => 'x@azatech.id', 'password' => 'salah'])->assertStatus(401);
    }

    $this->postJson('/api/auth/login', ['email' => 'x@azatech.id', 'password' => 'salah'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
