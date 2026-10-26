<?php

namespace App\Providers;


use App\Domains\CustomDomain;
use App\Domains\DomainRegistry;
use App\Domains\FootballDomain;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DomainRegistry::class, function () {
            $registry = new DomainRegistry;
            $registry->register(new CustomDomain);

            $registry->register(new FootballDomain);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('rooms', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($request->session()->getId()));
    }
}
