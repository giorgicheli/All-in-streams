<?php

namespace App\Providers;

use App\Services\SiteContent;
use App\Services\CreatorMediaProcessor;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SiteContent::class);
        $this->app->scoped(CreatorMediaProcessor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('password-email-ip', fn (Request $request) => Limit::perMinutes(10, 10)->by($request->ip()));
        RateLimiter::for('password-update-ip', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        Livewire::addPersistentMiddleware([\App\Http\Middleware\EnsurePartnerVerified::class]);
        View::composer(['components.brand', 'components.layout', 'components.auth-story', 'auth.login', 'auth.register', 'auth.forgot-password', 'auth.reset-password'], function ($view): void {
            $view->with('site', request()->attributes->get('website.settings') ?? app(SiteContent::class)->getPublished('settings'));
        });
    }
}
