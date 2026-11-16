<?php

namespace App\Providers;

use App\Services\SiteContent;
use App\Services\CreatorMediaProcessor;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
        Livewire::addPersistentMiddleware([\App\Http\Middleware\EnsurePartnerVerified::class]);
        View::composer(['components.brand', 'components.layout', 'components.auth-story', 'auth.login', 'auth.register'], function ($view): void {
            $view->with('site', request()->attributes->get('website.settings') ?? app(SiteContent::class)->getPublished('settings'));
        });
    }
}
