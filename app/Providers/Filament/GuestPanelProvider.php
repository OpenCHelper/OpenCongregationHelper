<?php

namespace App\Providers\Filament;

use Andreia\FilamentUiSwitcher\FilamentUiSwitcherPlugin;
use App\Http\Middleware\PreventSearchIndexing;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class GuestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('guest')
            ->path('guest')
            ->viteTheme('resources/css/filament/guest/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->themeSwitcher(false)
            // The panel only serves the shared pages: no dashboard nor navigation that would expose it to anyone
            ->navigation(false)
            ->homeUrl('/')
            // ->resources([
            //     // Resources can be added here if needed
            // ])
            ->discoverResources(in: app_path('Filament/Guest/Resources'), for: 'App\\Filament\\Guest\\Resources')
            ->discoverPages(in: app_path('Filament/Guest/Pages'), for: 'App\\Filament\\Guest\\Pages')
            ->discoverWidgets(in: app_path('Filament/Guest/Widgets'), for: 'App\\Filament\\Guest\\Widgets')
            ->plugins([
                FilamentUiSwitcherPlugin::make()
                    ->withModeSwitcher(),
            ])
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
                PreventSearchIndexing::class,
            ])
            // Also for the crawlers that read only the page, besides the header of `PreventSearchIndexing`
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => '<meta name="robots" content="'.PreventSearchIndexing::DIRECTIVES.'">',
            )
            ->authMiddleware([
                // Authenticate::class,
            ]);
    }
}
