<?php

namespace App\Providers;

use App\Services\SiteContent;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SiteContent::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['components.brand', 'components.layout', 'components.auth-story', 'auth.login', 'auth.register'], function ($view): void {
            $view->with('site', request()->attributes->get('website.settings') ?? app(SiteContent::class)->getPublished('settings'));
        });
    }
}
