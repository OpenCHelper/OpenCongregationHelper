<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    config([
        'app.initial_root_user.email' => 'root@example.com',
        'app.initial_root_user.password' => 'DummyPassword',
        'app.demo_prefill_login_credentials' => true,
    ]);

    app()->detectEnvironment(fn () => 'preview');
});

it('uses the custom login page on the app panel', function () {
    expect(Filament::getPanel('app')->getLoginRouteAction())->toBe(Login::class);
});

it('pre-fills the credentials of the root user on demos', function () {
    Livewire::test(Login::class)
        ->assertSchemaStateSet([
            'email' => 'root@example.com',
            'password' => 'DummyPassword',
            'remember' => true,
        ]);
});

it('does not focus the email field when the credentials are pre-filled', function () {
    $emailField = Livewire::test(Login::class)->instance()->form->getFlatFields()['email'];

    expect($emailField->isAutofocused())->toBeFalse();
});

it('focuses the email field when the credentials are not pre-filled', function () {
    config(['app.demo_prefill_login_credentials' => false]);

    $emailField = Livewire::test(Login::class)->instance()->form->getFlatFields()['email'];

    expect($emailField->isAutofocused())->toBeTrue();
});

it('logs in as the root user with the pre-filled credentials', function () {
    $rootUser = User::factory()
        ->withPassword('DummyPassword')
        ->create(['id' => 1, 'email' => 'root@example.com']);

    Livewire::test(Login::class)
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($rootUser);
});

it('leaves the form empty when the demo flag is disabled', function () {
    config(['app.demo_prefill_login_credentials' => false]);

    Livewire::test(Login::class)
        ->assertSchemaStateSet([
            'email' => null,
            'password' => null,
        ]);
});

it('leaves the form empty in production, even with the demo flag enabled', function () {
    app()->detectEnvironment(fn () => 'production');

    Livewire::test(Login::class)
        ->assertSchemaStateSet([
            'email' => null,
            'password' => null,
        ]);
});

it('leaves the form empty when the credentials of the root user are not set', function () {
    config([
        'app.initial_root_user.email' => null,
        'app.initial_root_user.password' => null,
    ]);

    Livewire::test(Login::class)
        ->assertSchemaStateSet([
            'email' => null,
            'password' => null,
        ]);
});
