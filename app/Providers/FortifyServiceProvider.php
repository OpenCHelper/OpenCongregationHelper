<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * The routes of Fortify are not registered, as the Filament panel handles the authentication (login, password
     * reset, email verification and profile). This must happen here, because Fortify registers its routes while
     * booting, before this provider boots.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        $this->configureNotificationUrls();
    }

    /**
     * Point the links of the default password reset and email verification notifications to the pages of the panel.
     *
     * By default they link to the `password.reset` and `verification.verify` routes of Fortify, which are not
     * registered. The verification is sent, for example, when the user changes the email from the profile.
     */
    protected function configureNotificationUrls(): void
    {
        ResetPassword::createUrlUsing(
            fn (CanResetPassword $user, string $token): string => Filament::getPanel('app')->getResetPasswordUrl($token, $user),
        );

        VerifyEmail::createUrlUsing(
            fn (MustVerifyEmail $user): string => Filament::getPanel('app')->getVerifyEmailUrl($user),
        );
    }
}
