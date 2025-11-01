<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = 'login:'.strtolower((string)$request->input('name')).'|'.$request->ip();
            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('register', fn (Request $r) =>
        Limit::perMinute(5)->by($r->ip())
        );

        RateLimiter::for('game', fn (Request $r) =>
        Limit::perMinute(120)->by($r->user()?->id ?? $r->ip())
        );
    }
}
