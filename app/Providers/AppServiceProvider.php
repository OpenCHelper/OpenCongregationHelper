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
        
        Mail::extend('nativemailfx', function (array $config = []) {
            return new NativeMailFxTransport(/* ... */);
        });

        if ($this->app->environment('local')) {
            Event::listen('*', function ($event, array $data) {
                if (
                    $event == 'Illuminate\\Log\\Events\\MessageLogged'
                    || str_starts_with($event, 'composing:')
                    || str_starts_with($event, 'creating:')
                ) {
                    return;
                }
                
                Log::debug("Event fired: '$event'", /* [
                    'data' => $data,
                ] */);
            });
        }
    
        Gate::define('view-logs', function (User $user) {
            return $user->hasRole('super_admin')
                ? Response::allow()
                : Response::denyWithStatus(404);
        });

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['en','it']) // also accepts a closure
                ->flags([
                    'en' => asset('vendor/blade-flags/language-en.svg'),
                    'it' => asset('vendor/blade-flags/language-it.svg'),
                ])
            ;
        });

        // Set default timezone for DateTimePicker
        DateTimePicker::configureUsing(function (DateTimePicker $component): void {
            $component->timezone(Auth::user()?->timezone ?? config('app.timezone'));
        });

        // Set default timezone for TextColumn for datetime attibutes
        TextColumn::configureUsing(function (TextColumn $component): void {
            if (in_array($component->getName(), [
                'created_at', 'updated_at', 'published_at', 'email_verified_at',
                'deleted_at', 'last_login_at', 'last_activity_at', 'last_seen_at',
                'date_start', 'date_end',
            ])) {
                $component->timezone(Auth::user()?->timezone ?? config('app.timezone'));
            }
        });
    }
}
