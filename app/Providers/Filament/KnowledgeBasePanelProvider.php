<?php

namespace App\Providers\Filament;

use Andreia\FilamentUiSwitcher\FilamentUiSwitcherPlugin;
use Filament\Facades\Filament;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBasePlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class KnowledgeBasePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('knowledge-base')
            ->path('knowledge-base')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->themeSwitcher(false)
            ->viteTheme('resources/css/filament/knowledge-base/theme.css')
            ->discoverResources(in: app_path('Filament/KnowledgeBase/Resources'), for: 'App\Filament\KnowledgeBase\Resources')
            ->discoverPages(in: app_path('Filament/KnowledgeBase/Pages'), for: 'App\Filament\KnowledgeBase\Pages')
            ->pages([
                // Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/KnowledgeBase/Widgets'), for: 'App\Filament\KnowledgeBase\Widgets')
            ->plugins([
                KnowledgeBasePlugin::make(base_path('docs/user-documentation'))
                    ->backUrl(fn (): ?string => $this->getBackUrl()),
                FilamentUiSwitcherPlugin::make()
                    ->iconRenderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER)
                    ->withModeSwitcher(),
            ])
            ->userMenu(false)
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
            ->authMiddleware([]);
    }

    /**
     * Get the URL of the "Back" button at the bottom of the documentation sidebar.
     *
     * The documentation is public, so visitors go back to the welcome page instead of the login page of the app.
     */
    protected function getBackUrl(): ?string
    {
        $appPanel = Filament::getPanel('app');

        if ($appPanel->auth()->check()) {
            return KnowledgeBase::url($appPanel);
        }

        return url('/');
    }
}
