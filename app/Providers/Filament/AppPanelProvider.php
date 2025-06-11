<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ApiTokens;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Pages\CreateTeam;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\EditTeam;
use App\Listeners\SwitchTeam;
use App\Models\Team;
use App\Providers\AppVersionProvider;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Features as FortifyFeatures;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Jetstream;
use \FilipFonal\FilamentLogManager\FilamentLogManager;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;
use Awcodes\FilamentVersions\VersionsPlugin;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentMailLog\FilamentMailLogPlugin;
use Rmsramos\Activitylog\ActivitylogPlugin;

use Filament\Support\Enums\Platform;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->passwordReset()
            ->emailVerification(isRequired: false)
            ->viteTheme('resources/css/app.css')
            ->colors([
                'primary' => Color::Gray,
            ])
            ->unsavedChangesAlerts()
            ->databaseNotifications()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix(fn (): ?string => match (Platform::detect()) {
                Platform::Windows, Platform::Linux => 'CTRL+K',
                Platform::Mac => '⌘K',
                default => null,
            })
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn() => __('filament-resources.user_profile.menu_label'))
                    ->icon('heroicon-o-user-circle')
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
            ->plugins([
                FilamentShieldPlugin::make(),
                FilamentLogManager::make(),
                EnvironmentIndicatorPlugin::make()
                    ->color(fn () => match (app()->environment()) {
                        'local' => Color::Blue,             # 1. Local development machine
                        'nightly' => Color::Amber,          # 2. First stage (nightly)
                        'staging', 'uat' => Color::Orange,  # 3. Pre-production
                        'production' => Color::Red,         # 4. Production
                        default => Color::Fuchsia,          # (unknown)
                    }),
                \DiscoveryDesign\FilamentGaze\FilamentGazePlugin::make(),
                VersionsPlugin::make()
                    ->hasDefaults(App::environment() !== "production")
                    ->items([
                        new AppVersionProvider(),
                    ]),
                FilamentMailLogPlugin::make(),
                ActivitylogPlugin::make(),
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

        if (app()->environment(["local", "nightly"])) {
            $panel->plugins([
                \TomatoPHP\FilamentArtisan\FilamentArtisanPlugin::make(),
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
                    ->icon('heroicon-o-key')
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
                        ->icon('heroicon-o-cog-6-tooth')
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
        $hasVerifiedEmail = Auth::user()?->hasVerifiedEmail();

        return Filament::hasTenancy()
            ? $hasVerifiedEmail && Filament::getTenant()
            : $hasVerifiedEmail;
    }
}
