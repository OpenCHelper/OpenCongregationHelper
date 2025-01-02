<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Illuminate\Support\Facades\Log;

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
        Log::debug(asset('vendor/blade-flags/'));
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
