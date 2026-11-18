<?php

namespace App\Providers;

use App\Console\Commands\PrefixAwareWipeCommand;
use App\Models\Tag;
use App\Models\User;
use App\NativeMailFxTransport;
use App\Policies\ActivityPolicy;
use App\Policies\MailLogPolicy;
use App\Policies\TagPolicy;
use App\Support\SettingsOverrides;
use BezhanSalleh\LanguageSwitch\Http\Middleware\SwitchLanguageLocale;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\TextColumn;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Access\Response;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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

        $this->app->singleton(SettingsOverrides::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Apply the settings changed from the Settings pages, except while caching the configuration (from the shell or
        // from the web): the cache must hold the values of `.env`, the defaults of the settings.
        Event::listen(function (CommandStarting $event): void {
            if (in_array($event->command, SettingsOverrides::CONFIG_CACHING_COMMANDS, true)) {
                SettingsOverrides::cachingConfiguration(true);
            }
        });
        Event::listen(function (CommandFinished $event): void {
            if (in_array($event->command, SettingsOverrides::CONFIG_CACHING_COMMANDS, true)) {
                SettingsOverrides::cachingConfiguration(false);
            }
        });

        $this->app->make(SettingsOverrides::class)->apply();

        // Long-running queue workers apply the settings saved since they started before each job
        Queue::before(fn (): mixed => $this->app->make(SettingsOverrides::class)->apply());

        // Tell Eloquent to be strict about the data types it expects
        // locally.
        // https://laraveldaily.com/lesson/laravel-eloquent-expert/model-fillable-guarded/
        Model::shouldBeStrict(! app()->isProduction());

        FilamentAsset::register([
            Js::make('filament-sidebar-fix', __DIR__.'/../../resources/js/filament-sidebar-fix.js'),
            Js::make('filament-map-picker-fix', __DIR__.'/../../resources/js/filament-map-picker-fix.js'),
        ]);

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
    }
}
