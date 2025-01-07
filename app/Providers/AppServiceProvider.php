<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Illuminate\Database\Eloquent\Model;

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
