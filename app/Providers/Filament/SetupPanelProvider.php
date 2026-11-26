<?php

namespace App\Providers\Filament;

use Andreia\FilamentUiSwitcher\FilamentUiSwitcherPlugin;
use App\Filament\Setup\Pages\SetupWizard;
use App\Http\Middleware\PreventSearchIndexing;
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

/**
 * The panel of the setup wizard (`/setup`), which installs the application from the browser while it is not installed
 * (see `SetupWizard`): without login, as there are no users yet, and without anything else to open.
 */
class SetupPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('setup')
            ->path('setup')
            // The pages of the panel are all public and need nothing of their own: the theme of the guest pages
            ->viteTheme('resources/css/filament/guest/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->themeSwitcher(false)
            ->navigation(false)
            ->homeUrl('/')
            ->pages([
                SetupWizard::class,
            ])
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
            );
    }
}
