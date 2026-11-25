<?php

namespace App\Providers;

use App\Console\Commands\PrefixAwareWipeCommand;
use App\Models\Tag;
use App\Models\User;
use App\NativeMailFxTransport;
use App\Policies\ActivityPolicy;
use App\Policies\MailLogPolicy;
use App\Policies\TagPolicy;
use App\Settings\SecuritySettings;
use App\Support\AiAssistantConnections;
use App\Support\UserLocale;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Carbon\CarbonInterval;
use Filament\Actions\Exports\ExportColumn;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\TextColumn;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Translatable\Facades\Translatable;
use STS\FilamentImpersonate\Facades\Impersonation;
use Tapp\FilamentMailLog\Models\MailLog;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // `db:wipe` (also run by `migrate:fresh`) drops only the tables of this installation when the database is
        // shared (see the command). Extended, not bound: the deferred Artisan provider registers the command later.
        $this->app->extend(WipeCommand::class, fn (): PrefixAwareWipeCommand => new PrefixAwareWipeCommand);

        // The leaked-password check waits 30 seconds by default for Have I Been Pwned: on servers that cannot reach it,
        // every password change would hang that long. Extended for the same reason (the validation provider is deferred)
        $this->app->extend(UncompromisedVerifier::class, fn (UncompromisedVerifier $verifier, Application $app): UncompromisedVerifier => new NotPwnedVerifier(
            $app->make(HttpFactory::class),
            (int) config('auth.password_leak_check.timeout'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tell Eloquent to be strict about the data types it expects
        // locally.
        // https://laraveldaily.com/lesson/laravel-eloquent-expert/model-fillable-guarded/
        Model::shouldBeStrict(! app()->isProduction());

        FilamentAsset::register([
            Js::make('filament-sidebar-fix', __DIR__.'/../../resources/js/filament-sidebar-fix.js'),
            Js::make('filament-map-picker-fix', __DIR__.'/../../resources/js/filament-map-picker-fix.js'),
            Js::make('push-subscription', __DIR__.'/../../resources/js/push-subscription.js'),
        ]);

        // The map picker (`dotswan/filament-map-picker`) and the maps of `eduardoribeirodev/filament-leaflet` each
        // bundle their own Leaflet and set it as `window.L`, which their plugins (e.g. Geoman) use: loaded on the
        // same page, the one loaded last replaces the other, and the shapes drawn on a map are made with the other
        // Leaflet (drawing fails). Each script is then loaded only by the pages that show one of its maps.
        $this->loadMapScriptOnRequest(
            'eduardoribeirodev/filament-leaflet',
            ['filament-leaflet::components.map'],
            'filament.leaflet-map-assets',
        );
        $this->loadMapScriptOnRequest(
            'dotswan/filament-map-picker',
            ['map-picker::fields.osm-map-picker', 'map-picker::infolists.osm-map-entry'],
            'filament.map-picker-assets',
        );

        // Make every panel installable as a Progressive Web App (manifest, icons and service worker)
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render('<x-pwa-head />'),
        );

        // Add a custom mail transport that uses the native mail() function
        Mail::extend('nativemailfx', function (array $config = []) {
            return new NativeMailFxTransport/* ... */;
        });

        // Configure the gate to only allow super_admins to view logs
        Gate::define('view-logs', function (User $user) {
            return $user->hasRole('super_admin')
                ? Response::allow()
                : Response::denyWithStatus(404);
        });

        // The members of a department can do everything on its sections (see `DepartmentKey::getResources()`),
        // without needing a role with their permissions. Other abilities fall through to the roles.
        Gate::before(fn (mixed $user, string $ability): ?bool => $user instanceof User && $user->hasDepartmentPermission($ability) ? true : null);

        // The policies of models outside `App\Models` are not discovered, and Filament allows everything on a
        // resource whose model has no policy: without the MailLogPolicy, every user could read the emails sent.
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(MailLog::class, MailLogPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $flags = collect(config('app.available_locales'))->mapWithKeys(function ($longName, $shortName) {
                return [$shortName => asset("vendor/blade-flags/language-{$shortName}.svg")];
            })->toArray();

            $switch
                ->locales(array_keys($flags))
                ->flags($flags);
        });

        // Apply the language chosen by the user to Livewire requests too: Filament builds the page schemas while
        // the components are hydrated, before Livewire restores their locale, so they used the default locale.
        Livewire::addPersistentMiddleware([SwitchLanguageLocale::class]);

        // Register a fallback for missing translations (eg. when using laravel-tags)
        Translatable::fallback(
            fallbackAny: true,
            missingKeyCallback: function (
                Model $model,
                string $translationKey,
                string $locale,
                string $fallbackTranslation,
                string $fallbackLocale,
            ) {
                // Log::warning('Some translation key is missing from an eloquent model', [
                //    'key' => $translationKey,
                //    'locale' => $locale,
                //    'fallback_locale' => $fallbackLocale,
                //    'fallback_translation' => $fallbackTranslation,
                //    'model_id' => $model->id,
                //    'model_class' => get_class($model),
                // ]);
            }
        );

        // Set default timezone for DateTimePicker
        DateTimePicker::configureUsing(function (DateTimePicker $component): void {
            $component->timezone(Auth::user()?->timezone ?? config('app.timezone'));
        });

        // Set default timezone for TextColumn for datetime attibutes
        TextColumn::configureUsing(function (TextColumn $component): void {
            if (in_array($component->getName(), [
                'created_at', 'updated_at', 'published_at', 'email_verified_at',
                'deleted_at', 'last_login_at', 'last_activity_at', 'last_seen_at',
                'started_at', 'ended_at',
            ])) {
                $component->timezone(Auth::user()?->timezone ?? config('app.timezone'));
            }
        });

        $this->configureAiAssistants();

        $this->configurePasswordPolicy();

        // Exported cells that a spreadsheet would run as a formula (e.g. a title starting with `=`) are written as text
        ExportColumn::configureUsing(function (ExportColumn $column): void {
            $column->preventFormulaInjection();
        });
    }

    /**
     * The rules of the passwords chosen by the users (Fortify, the Filament password reset and invitation page, the
     * profile and the Artisan commands all use `Password::default()`).
     *
     * In production a password needs at least 12 characters, elsewhere the 8 characters of Laravel are enough. It must
     * not appear in a known data leak either (checked with the k-anonymity API of Have I Been Pwned, which only
     * receives the first characters of its SHA-1 hash), when the Security settings say so: by default
     * `PASSWORD_LEAK_CHECK`, itself on only in production, so that local and test environments do not call the API.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(app()->isProduction() ? 12 : 8)->max(255);

            return app(SecuritySettings::class)->password_leak_check ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * The OAuth authorization server (Passport) of the AI assistants connected to the MCP server (see `routes/ai.php`).
     *
     * The access tokens are short-lived and renewed by the assistants with their refresh token: an assistant unused for
     * longer than the refresh tokens must be authorized again. A revoked token stops working right away anyway.
     */
    private function configureAiAssistants(): void
    {
        // The keys are kept in `storage/` like the `.env` file, outside of `public/` (see the root `.htaccess`), but shared
        // hosting (e.g. Altervista) ignores `chmod`: the check of their permissions would fail every request with a 500
        Passport::$validateKeyPermissions = false;

        Passport::tokensExpireIn(CarbonInterval::hours(1));
        Passport::refreshTokensExpireIn(CarbonInterval::days(90));

        // The page where the user approves an assistant, which only the user can do (not an administrator
        // impersonating them), while the assistants are allowed
        Passport::authorizationView(function (array $parameters): HttpResponse {
            abort_unless(AiAssistantConnections::enabled(), 404);
            abort_if(Impersonation::isImpersonating(), 403, __('ai-assistants.authorize.impersonating'));

            // In the language of the user, as the page is outside of the panel (which applies it)
            return UserLocale::run($parameters['user'], fn (): HttpResponse => response()->view('mcp.authorize', $parameters));
        });

        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }

    /**
     * Load the scripts of a map plugin only on the pages that show one of its maps, instead of on every page.
     *
     * The views that render the maps render the given assets view, whose `@assets` block Livewire adds to the page
     * once, before the map is initialised (see `filament/partials/script-loaded-on-request.blade.php`).
     *
     * @param  list<string>  $mapViews
     */
    private function loadMapScriptOnRequest(string $package, array $mapViews, string $assetsView): void
    {
        foreach (FilamentAsset::getScripts([$package]) as $script) {
            $script->loadedOnRequest();
        }

        View::composer($mapViews, function () use ($assetsView): void {
            view($assetsView)->render();
        });
    }
}
