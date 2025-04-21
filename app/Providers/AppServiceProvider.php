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
    }
}
