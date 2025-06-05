<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;

use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\NativeMailFxTransport;

use App\Policies\ActivityPolicy;
use Spatie\Activitylog\Models\Activity;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 
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
        
        // Add a custom mail transport that uses the native mail() function
        Mail::extend('nativemailfx', function (array $config = []) {
            return new NativeMailFxTransport(/* ... */);
        });
    
        // Configure the gate to only allow super_admins to view logs
        Gate::define('view-logs', function (User $user) {
            return $user->hasRole('super_admin')
                ? Response::allow()
                : Response::denyWithStatus(404);
        });
        Gate::policy(Activity::class, ActivityPolicy::class);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $flags = collect(config('app.available_locales'))->mapWithKeys(function ($longName, $shortName) {
                return [$shortName => asset("vendor/blade-flags/language-{$shortName}.svg")];
            })->toArray();

            $switch
                ->locales(array_keys($flags))
                ->flags($flags)
            ;
        });
        
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
                Log::warning('Some translation key is missing from an eloquent model', [
                   'key' => $translationKey,
                   'locale' => $locale,
                   'fallback_locale' => $fallbackLocale,
                   'fallback_translation' => $fallbackTranslation,
                   'model_id' => $model->id,
                   'model_class' => get_class($model), 
                ]);
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
