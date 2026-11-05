<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Order\OrderStateMachine;
use App\Domain\Order\OrderTotalCalculator;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Class domain stateless → cukup satu instance.
        $this->app->singleton(OrderStateMachine::class);
        $this->app->singleton(OrderTotalCalculator::class);
    }

    public function boot(): void
    {
        // Di luar production: error jika ada lazy loading (deteksi N+1) atau mass assignment ke kolom non-fillable.
        Model::shouldBeStrict(! $this->app->isProduction());

        // 5 percobaan login / menit per kombinasi email + IP (mencegah brute force).
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        // 30 order / menit per user (cukup untuk kasir, membatasi script nakal).
        RateLimiter::for('orders', function (Request $request): Limit {
            return Limit::perMinute((int) config('app.order_rate_limit', 30))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
