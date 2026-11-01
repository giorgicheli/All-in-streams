<?php

namespace App\Providers\Filament;

use App\Filament\Pages\OperationsDashboard;
use App\Filament\Pages\WebsiteEditor;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('All In Streams')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            ->font('Inter')
            ->colors(['primary' => Color::Lime, 'success' => Color::Teal, 'gray' => Color::Zinc])
            ->defaultThemeMode(ThemeMode::Dark)
            ->darkMode(isForced: true)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->pages([OperationsDashboard::class, WebsiteEditor::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->renderHook('panels::head.end', fn () => new HtmlString('<style>.fi-body{background:#080d0e}.fi-sidebar,.fi-topbar{background:#0b1113}.fi-main{padding-top:2rem}.fi-logo{letter-spacing:-.035em}.fi-sidebar-item-active .fi-sidebar-item-button{border-left:3px solid #c4f853}.fi-section,.fi-ta{border-color:#263235!important}.fi-header-heading{letter-spacing:-.035em}</style>'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
