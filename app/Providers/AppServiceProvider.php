<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Turn an accidental N+1 into a loud failure everywhere but production.
        Model::preventLazyLoading(! app()->isProduction());

        // Per token, falling back to the caller's address for anything
        // that somehow reaches the API unauthenticated.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(
            (int) config('scraping.api_rate_limit', 60)
        )->by($request->user()?->id ?: $request->ip()));
    }
}
