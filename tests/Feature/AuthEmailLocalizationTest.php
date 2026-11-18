<?php

use App\Models\User;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    // The emails must follow the locale of the recipient, not the one of the request
    app()->setLocale('en');
});

/**
 * Get the emails sent through the array mailer.
 *
 * @return Collection<int, SentMessage>
 */
function sentEmails(): Collection
{
    return Mail::mailer()->getSymfonyTransport()->messages();
}

test('the password reset email is sent in the preferred language of the user', function (string $locale, string $subject, string $action) {
    $user = User::factory()->create(['locale' => $locale, 'can_login' => true]);

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request')
        ->assertHasNoFormErrors();

    $email = sentEmails()->sole()->getOriginalMessage();

    expect($email->getSubject())->toBe($subject)
        ->and($email->getHtmlBody())->toContain($action)
        ->and(app()->getLocale())->toBe('en');
})->with([
    'italian' => ['it', 'Reimpostazione della password', 'Reimposta password'],
    'spanish' => ['es', 'Restablecimiento de contraseña', 'Restablecer contraseña'],
    'english' => ['en', 'Reset Password Notification', 'Reset Password'],
]);

test('the email verification email is sent in the preferred language of the user', function () {
    $user = User::factory()->create(['locale' => 'it', 'email_verified_at' => null]);

    $user->notify(new VerifyEmail);

    $email = sentEmails()->sole()->getOriginalMessage();

    expect($email->getSubject())->toBe('Verifica indirizzo email')
        ->and($email->getHtmlBody())
        ->toContain('Fai clic sul pulsante qui sotto per verificare il tuo indirizzo email.')
        ->toContain('Saluti,')
        ->not->toContain('Regards,');
});

test('the JSON translations define the same keys for every available locale', function () {
    $keys = fn (string $locale): array => array_keys(json_decode(
        file_get_contents(lang_path("{$locale}.json")), associative: true, flags: JSON_THROW_ON_ERROR,
    ));

    foreach (array_keys(config('app.available_locales')) as $locale) {
        expect($keys($locale))->toEqualCanonicalizing($keys('en'));
    }
});
