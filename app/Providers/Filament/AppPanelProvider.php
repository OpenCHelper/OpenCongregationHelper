<?php

namespace App\Providers\Filament;

use Andreia\FilamentUiSwitcher\FilamentUiSwitcherPlugin;
use App\Enums\NavigationGroup as EnumsNavigationGroup;
use App\Filament\Pages\ApiTokens;
use App\Filament\Pages\CreateTeam;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\EditTeam;
use App\Listeners\SwitchTeam;
use App\Models\Team;
use App\Providers\AppVersionProvider;
use Awcodes\Versions\VersionsPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Detection\MobileDetect;
use DiscoveryDesign\FilamentGaze\FilamentGazePlugin;
use Filament\Enums\GlobalSearchPosition;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Platform;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use FilipFonal\FilamentLogManager\FilamentLogManager;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBaseCompanionPlugin;
use Hammadzafar05\FilamentMobilePreset\FilamentMobilePresetPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Fortify\Features as FortifyFeatures;
use Laravel\Fortify\Fortify;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Jetstream;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Tapp\FilamentMailLog\FilamentMailLogPlugin;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $detect = new MobileDetect;

        $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->passwordReset()
            ->emailVerification(isRequired: false)
            ->viteTheme('resources/css/filament/app/theme.css')
            ->colors([
                'primary' => Color::Gray,
            ])
            ->themeSwitcher(false)
            ->unsavedChangesAlerts()
            ->databaseNotifications()
            ->globalSearch(position: GlobalSearchPosition::Topbar)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix(fn (): ?string => match (Platform::detect()) {
                Platform::Windows, Platform::Linux => 'CTRL+K',
                Platform::Mac => '⌘K',
                default => null,
            })
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => __('filament-resources.user_profile.menu_label'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn () => $this->shouldRegisterMenuItem()
                        ? url(EditProfile::getUrl())
                        : url($panel->getPath())),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                // Pages\Dashboard::class,
                EditProfile::class,
                ApiTokens::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->navigationGroups(array_map(static fn (EnumsNavigationGroup $el) => $el->name, EnumsNavigationGroup::cases()))
            ->plugins([
                FilamentShieldPlugin::make(),
                FilamentLogManager::make(),
                EnvironmentIndicatorPlugin::make()
                    ->color(fn () => match (app()->environment()) {
                        'local' => Color::Blue,             // 1. Local development machine
                        'nightly' => Color::Amber,          // 2. First stage (nightly)
                        'staging', 'uat' => Color::Orange,  // 3. Pre-production
                        'production' => Color::Red,         // 4. Production
                        default => Color::Fuchsia,          // (unknown)
                    }),
                FilamentGazePlugin::make(),
                VersionsPlugin::make()
                    ->hasDefaults(App::environment() !== 'production')
                    ->items([
                        new AppVersionProvider,
                    ]),
                FilamentMailLogPlugin::make(),
                ActivitylogPlugin::make(),
                FilamentMobilePresetPlugin::make()
                    ->stackedTables(false)
                    ->slideOverModals($detect->isMobile()),
                KnowledgeBaseCompanionPlugin::make()
                    ->knowledgeBasePanelId('knowledge-base')
                    ->helpMenuRenderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE)
                    ->modalPreviews()
                    ->modalTitleBreadcrumbs()
                    ->slideOverPreviews(),
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        if (app()->environment(['local', 'nightly'])) {
            $panel->plugins([
                // \TomatoPHP\FilamentArtisan\FilamentArtisanPlugin::make(),
            ]);
        }

        // Dynamically add registration views based on the fortify
        // configuration file.
        if (FortifyFeatures::enabled(FortifyFeatures::registration())) {
            $panel->registration();
        }

        if (Features::hasApiFeatures()) {
            $panel->userMenuItems([
                MenuItem::make()
                    ->label('API Tokens')
                    ->icon(Heroicon::OutlinedKey)
                    ->url(fn () => $this->shouldRegisterMenuItem()
                        ? url(ApiTokens::getUrl())
                        : url($panel->getPath())),
            ]);
        }

        if (Features::hasTeamFeatures()) {
            $panel
                ->tenant(Team::class)
                ->tenantRegistration(CreateTeam::class)
                ->tenantProfile(EditTeam::class)
                ->userMenuItems([
                    MenuItem::make()
                        ->label(fn () => __('Team Settings'))
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->url(fn () => $this->shouldRegisterMenuItem()
                            ? url(EditTeam::getUrl())
                            : url($panel->getPath())),
                ]);
        }

        return $panel;
    }

    public function boot()
    {
        /**
         * Disable Fortify routes
         */
        Fortify::$registersRoutes = false;

        /**
         * Disable Jetstream routes
         */
        Jetstream::$registersRoutes = false;

        /**
         * Listen and switch team if tenant was changed
         */
        Event::listen(
            TenantSet::class,
            SwitchTeam::class,
        );
    }

    public function shouldRegisterMenuItem(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $hasVerifiedEmail = $user->email_verified_at !== null;

        // Allow the profile page to be accessible even to users without a verified email
        // when tenancy is NOT enabled.
        return Filament::hasTenancy()
            ? $hasVerifiedEmail && Filament::getTenant()
            : true;
    }
}
