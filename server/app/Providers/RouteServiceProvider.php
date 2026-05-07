<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        Route::prefix('api/v1/auth')
            ->middleware('api')
            ->group(base_path('routes/api.auth.php'));

        Route::prefix('api/v1')
            ->middleware('api')
            ->group([
                base_path('routes/api.catogories.php'),
                base_path('routes/api.products.php')
            ]);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function(Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
