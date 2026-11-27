<?php

use App\Filament\Resources\MailLogs\MailLogResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tapp\FilamentMailLog\Models\MailLog;
use Tapp\FilamentMailLog\Resources\MailLogResource\Pages\ListMailLogs;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    // Render the real assets when they are built (as in CI), and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    // The mail log keeps the whole body of every email sent, password reset links included
    $this->mailLog = MailLog::create([
        'from' => 'noreply@example.com',
        'to' => 'admin@example.com',
        'subject' => 'Reset Password Notification',
        'body' => '<a href="https://example.com/reset-password/secret-reset-token">Reset Password</a>',
        'headers' => "From: noreply@example.com\r\nTo: admin@example.com\r\nSubject: Reset Password Notification",
        'message_id' => (string) Str::uuid(),
    ]);
});

test('users without a role cannot open the mail log', function () {
    $this->actingAs(User::factory()->create(['can_login' => true, 'email_verified_at' => now()]));

    $this->get(MailLogResource::getUrl())
        ->assertForbidden()
        ->assertDontSee('Reset Password Notification');

    Livewire::test(ListMailLogs::class)
        ->assertForbidden();
});

test('no permission of a role opens the mail log', function () {
    $user = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:MailLog', 'web'), Permission::findOrCreate('View:MailLog', 'web'));

    $this->actingAs($user);

    $this->get(MailLogResource::getUrl())
        ->assertForbidden()
        ->assertDontSee('Reset Password Notification');

    Livewire::test(ListMailLogs::class)
        ->assertForbidden();
});

test('super administrators can open the mail log and view an email', function () {
    $superAdmin = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

    $this->actingAs($superAdmin);

    $this->get(MailLogResource::getUrl())
        ->assertOk()
        ->assertSee('Reset Password Notification');

    Livewire::test(ListMailLogs::class)
        ->assertCanSeeTableRecords([$this->mailLog])
        ->assertActionVisible(TestAction::make('view')->table($this->mailLog))
        ->mountAction(TestAction::make('view')->table($this->mailLog))
        ->assertActionMounted(TestAction::make('view')->table($this->mailLog))
        ->assertMountedActionModalSee('secret-reset-token');
});
