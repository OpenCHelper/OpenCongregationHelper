<?php

namespace App\Providers\Filament;

use AchyutN\FilamentLogViewer\FilamentLogViewer;
use Andreia\FilamentUiSwitcher\FilamentUiSwitcherPlugin;
use App\Enums\NavigationGroup as EnumsNavigationGroup;
use App\Filament\Pages\ApiTokens;
use App\Filament\Pages\Auth\EmailVerificationPrompt;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\CreateTeam;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\EditTeam;
use App\Filament\Pages\Logs;
use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Middleware\EnsureMultiFactorAuthenticationWhenRequired;
use App\Listeners\SwitchTeam;
use App\Livewire\PushNotificationsPrompt;
use App\Models\Team;
use App\Settings\GeneralSettings;
use App\Support\AppVersions;
use App\Support\BugReport;
use App\Support\WebPush;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Detection\MobileDetect;
use DiscoveryDesign\FilamentGaze\FilamentGazePlugin;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\GlobalSearchPosition;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Platform;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBaseCompanionPlugin;
use Hammadzafar05\FilamentMobilePreset\FilamentMobilePresetPlugin;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Fortify\Features as FortifyFeatures;
use Laravel\Jetstream\Features;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Tapp\FilamentMailLog\FilamentMailLogPlugin;

class AppPanelProvider extends PanelProvider
{
    /**
     * Render hook of the panel with the UI preferences (mode, layout, color and font).
     *
     * The panel is opened from the user menu, so the button of the plugin is hidden (see the theme of the panel).
     */
    private const UI_PREFERENCES_RENDER_HOOK = 'app::ui-preferences';

    /**
     * Render hook of the help menu of the knowledge base.
     *
     * The menu is wrapped before the global search so that the theme of the panel can make its button compact.
     */
    private const HELP_MENU_RENDER_HOOK = 'app::help-menu';

    /**
     * Days during which the offer to install the app is not shown again, after the user dismissed it.
     */
    public const PWA_INSTALL_DISMISS_DAYS = 30;

    /**
     * Session key set when the user logs in (see `OfferFullScreenPromptsAfterLogin`), and taken by the first page, which
     * opens the offer to install the app by itself.
     */
    public const PWA_INSTALL_OFFER_SESSION_KEY = 'pwa_install_prompt.offer';

    public function panel(Panel $panel): Panel
    {
        $detect = new MobileDetect;

        $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login(Login::class)
            ->passwordReset(resetAction: ResetPassword::class)
            // Both are always required at the level of the routes, so that their middleware runs on every page: the
            // middleware then reads the Security settings on each request (cached routes stay valid when they change).
            ->emailVerification(EmailVerificationPrompt::class)
            ->emailVerifiedMiddlewareName(EnsureEmailIsVerifiedWhenRequired::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true)
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsureMultiFactorAuthenticationWhenRequired::class)
            ->viteTheme('resources/css/filament/app/theme.css')
            ->colors([
                'primary' => Color::Gray,
            ])
            ->brandLogo(fn (GeneralSettings $settings): ?string => filled($logo = $settings->logo) ? Storage::disk('public')->url($logo) : null)
            ->brandLogoHeight('2rem')
            ->themeSwitcher(false)
            ->sidebarCollapsibleOnDesktop()
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
                // Named `profile` so that Filament uses it as the profile item instead of adding its own.
                Action::make('profile')
                    ->label(fn (): string => __('filament-resources.user_profile.menu_label'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => $this->shouldRegisterMenuItem()
                        ? url(EditProfile::getUrl())
                        : url($panel->getPath())),
                Action::make('uiPreferences')
                    ->label(fn (): string => __('filament-ui-switcher::filament-ui-switcher.modal.heading'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->alpineClickHandler("\$dispatch('open-modal', { id: 'ui-switcher-modal' })"),
                // Opens the offer to install the app (`filament.pwa-install-prompt`), only while the browser can install it
                Action::make('installApp')
                    ->label(fn (): string => __('pwa.install.menu_label'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->alpineClickHandler("\$dispatch('open-pwa-install')")
                    ->extraAttributes(['x-show' => '$store.pwaInstall.available', 'x-cloak' => true]),
                Action::make('versionInfo')
                    ->label(fn (): string => __('filament-resources.version_info.menu_label'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->modalHeading(fn (): string => __('filament-resources.version_info.modal_heading'))
                    ->modalIcon(Heroicon::OutlinedInformationCircle)
                    ->modalWidth(Width::Medium)
                    ->modalContent(fn (): View => view('filament.version-info', [
                        'versions' => AppVersions::all(),
                        'build' => AppVersions::build(),
                    ]))
                    ->modalSubmitAction(false)
                    ->extraModalFooterActions([
                        Action::make('reportBug')
                            ->label(fn (): string => __('filament-resources.bug_report.button_label'))
                            ->icon(Heroicon::OutlinedBugAnt)
                            ->color('gray')
                            ->url(fn (): string => BugReport::url(request()))
                            ->openUrlInNewTab(),
                    ])
                    ->modalCancelActionLabel(fn (): string => __('filament-actions::view.single.modal.actions.close.label')),
            ])
            ->renderHook(
                PanelsRenderHook::LAYOUT_END,
                fn (): string => '<div class="app-ui-preferences">'.FilamentView::renderHook(self::UI_PREFERENCES_RENDER_HOOK)->toHtml().'</div>',
            )
            // Offer to install the app on the first page after logging in (on the login page it would cover the form),
            // at the start of the body to catch the event of the browser early. Rendered on the other pages too, where it
            // opens only from the user menu and the profile.
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): ?View => Filament::auth()->check()
                    ? view('filament.pwa-install-prompt', [
                        'offerAfterLogin' => (bool) session()->pull(self::PWA_INSTALL_OFFER_SESSION_KEY),
                    ])
                    : null,
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): ?View => Filament::auth()->check() && WebPush::isConfigured() ? view('filament.push-logout') : null,
            )
            // Offer to enable the push notifications on the device on the first page after logging in, taking turns with
            // the offer to install
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): ?string => Filament::auth()->check()
                    && WebPush::canSubscribe()
                    && session()->pull(PushNotificationsPrompt::OFFER_SESSION_KEY)
                        ? Blade::render("@livewire('push-notifications-prompt')")
                        : null,
            )
            // Registered after the plugins, so that the help menu stays after the environment indicator.
            ->bootUsing(function (): void {
                FilamentView::registerRenderHook(
                    PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                    fn (): string => '<div class="app-help-menu">'.FilamentView::renderHook(self::HELP_MENU_RENDER_HOOK)->toHtml().'</div>',
                );
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
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
                FilamentLogViewer::make()
                    ->pageClass(Logs::class)
                    ->authorize(fn (): bool => Gate::allows('view-logs'))
                    ->navigationGroup(EnumsNavigationGroup::Developer->name)
                    ->navigationIcon(Heroicon::OutlinedServer),
                EnvironmentIndicatorPlugin::make()
                    ->color(fn () => self::environmentColor(app()->environment())),
                FilamentGazePlugin::make(),
                FilamentMailLogPlugin::make(),
                ActivitylogPlugin::make(),
                FilamentMobilePresetPlugin::make()
                    ->stackedTables(false)
                    ->slideOverModals($detect->isMobile()),
                KnowledgeBaseCompanionPlugin::make()
                    ->knowledgeBasePanelId('knowledge-base')
                    ->helpMenuRenderHook(self::HELP_MENU_RENDER_HOOK)
                    ->modalPreviews()
                    ->modalTitleBreadcrumbs()
                    ->slideOverPreviews(),
                FilamentUiSwitcherPlugin::make()
                    // The primary colour of the users who did not choose one in their UI preferences
                    ->defaults(fn (): array => array_filter(['color' => app(GeneralSettings::class)->primary_color]))
                    ->iconRenderHook(self::UI_PREFERENCES_RENDER_HOOK)
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
                Action::make('apiTokens')
                    ->label('API Tokens')
                    ->icon(Heroicon::OutlinedKey)
                    ->url(fn (): string => $this->shouldRegisterMenuItem()
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
                    Action::make('teamSettings')
                        ->label(fn (): string => __('Team Settings'))
                        ->icon(Heroicon::OutlinedUserGroup)
                        ->url(fn (): string => $this->shouldRegisterMenuItem()
                            ? url(EditTeam::getUrl())
                            : url($panel->getPath())),
                ]);
        }

        return $panel;
    }

    /**
     * Color of the environment indicator shown in the top bar for the given environment (`APP_ENV`).
     *
     * @return array<int|string, string>
     */
    public static function environmentColor(string $environment): array
    {
        return match ($environment) {
            'local' => Color::Blue,             // 1. Local development machine
            'nightly' => Color::Amber,          // 2. First stage (nightly)
            'preview', 'demo' => Color::Purple, // 2. Pull request previews and public demo, with dummy data
            'staging', 'uat' => Color::Orange,  // 3. Pre-production
            'production' => Color::Red,         // 4. Production
            default => Color::Fuchsia,          // (unknown)
        };
    }

    public function boot()
    {
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
