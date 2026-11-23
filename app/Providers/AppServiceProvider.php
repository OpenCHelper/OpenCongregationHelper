<?php

namespace App\Providers;

use App\Console\Commands\PrefixAwareWipeCommand;
use App\Models\Tag;
use App\Models\User;
use App\NativeMailFxTransport;
use App\Policies\ActivityPolicy;
use App\Policies\MailLogPolicy;
use App\Policies\TagPolicy;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Actions\Exports\ExportColumn;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\TextColumn;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Translatable\Facades\Translatable;
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

        // Exported cells that a spreadsheet would run as a formula (e.g. a title starting with `=`) are written as text
        ExportColumn::configureUsing(function (ExportColumn $column): void {
            $column->preventFormulaInjection();
        });
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
