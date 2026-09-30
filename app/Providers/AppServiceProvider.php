<?php

namespace App\Providers;

use App\Services\PhotoSearch\NullPhotoMatcher;
use App\Services\PhotoSearch\PhotoMatcher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PhotoMatcher::class, NullPhotoMatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('person-requests', fn (Request $request): Limit => Limit::perHour(5)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('photo-search', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        // Frenan el copiado masivo del catálogo. Los topes son altos a propósito:
        // en muchas redes móviles miles de personas comparten una dirección IP.
        RateLimiter::for('records', fn (Request $request): Limit => Limit::perMinute(240)->by($request->ip()));

        RateLimiter::for('statistics', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('photos', fn (Request $request): Limit => Limit::perMinute(1200)->by($request->ip()));
    }
}
