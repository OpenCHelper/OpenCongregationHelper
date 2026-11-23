<?php

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\EditProfile;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Livewire\ProfilePushNotifications;
use App\Livewire\PushNotificationsPrompt;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\CampaignCreatedNotification;
use App\Notifications\StaleAssignmentNotification;
use App\Notifications\TerritoryAssignedNotification;
use App\Notifications\TerritoryReturnedNotification;
use App\Notifications\TestPushNotification;
use App\Support\WebPush;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;
use STS\FilamentImpersonate\Facades\Impersonation;

beforeEach(function () {
    config([
        'webpush.vapid.public_key' => 'BPublicKeyOfTheServer',
        'webpush.vapid.private_key' => 'PrivateKeyOfTheServer',
    ]);

    // The user with ID 1 is the root user
    User::factory()->create(['id' => 1]);

    Filament::setCurrentPanel('app');

    // Creating campaigns and assignments notifies the users: nothing must reach a real push service
    Notification::fake();

    $this->user = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);
    $this->subscription = [
        'endpoint' => 'https://push.example.com/send/abc123',
        'keys' => ['p256dh' => 'public-key-of-the-browser', 'auth' => 'auth-secret'],
        'contentEncoding' => 'aes128gcm',
    ];
});

/**
 * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}}  $subscription
 */
function subscribe(User $user, array $subscription): void
{
    $user->updatePushSubscription($subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth']);
}

/**
 * Assign a new territory to a new user.
 */
function pushNotifiedAssignment(): TerritoryAssignment
{
    return TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => User::factory()->create()->id,
        'ended_at' => null,
    ]);
}

test('push notifications are configured only with both VAPID keys', function (?string $publicKey, ?string $privateKey, bool $isConfigured) {
    config(['webpush.vapid.public_key' => $publicKey, 'webpush.vapid.private_key' => $privateKey]);

    expect(WebPush::isConfigured())->toBe($isConfigured);
})->with([
    'both keys' => ['public', 'private', true],
    'no public key' => [null, 'private', false],
    'no private key' => ['public', '', false],
    'no keys' => [null, null, false],
]);

test('notifications are sent as push notifications to users who enabled them', function (BaseNotification $notification) {
    subscribe($this->user, $this->subscription);

    expect($notification->via($this->user))->toBe(['mail', 'database', WebPushChannel::class])
        ->and($notification->via(User::factory()->create()))->toBe(['mail', 'database']);
})->with([
    'campaign created' => fn () => new CampaignCreatedNotification(Campaign::factory()->create()),
    'stale assignment' => fn () => new StaleAssignmentNotification(pushNotifiedAssignment()),
    'territory assigned' => fn () => new TerritoryAssignedNotification(pushNotifiedAssignment()),
    'territory returned' => fn () => new TerritoryReturnedNotification(pushNotifiedAssignment()),
]);

test('users who cannot log in get no push notifications', function () {
    subscribe($this->user, $this->subscription);
    User::withoutEvents(fn () => $this->user->forceFill(['can_login' => false])->save());

    $notification = new TerritoryAssignedNotification(pushNotifiedAssignment());

    expect($notification->via($this->user))->toBe(['mail', 'database']);
});

test('the subscriptions are deleted when the user can no longer log in', function () {
    subscribe($this->user, $this->subscription);

    $this->user->update(['first_name' => 'Renamed']);

    expect($this->user->pushSubscriptions()->exists())->toBeTrue();

    $this->user->update(['can_login' => false]);

    expect($this->user->pushSubscriptions()->exists())->toBeFalse();
});

test('push notifications cannot be enabled while impersonating', function () {
    Impersonation::shouldReceive('isImpersonating')->andReturn(true);
    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)
        ->assertDontSee(__('profile.push_notifications.title'))
        ->call('subscribe', $this->subscription)
        ->assertNotFound();

    expect($this->user->pushSubscriptions()->exists())->toBeFalse();
});

test('notifications are not sent as push notifications when they are not configured', function () {
    config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
    subscribe($this->user, $this->subscription);

    $notification = new TerritoryAssignedNotification(pushNotifiedAssignment());

    expect($notification->via($this->user))->toBe(['mail', 'database']);
});

test('push notifications show the database notification and open its page', function () {
    $assignment = pushNotifiedAssignment();

    $message = (new TerritoryAssignedNotification($assignment))->toWebPush($this->user, new TestPushNotification)->toArray();

    expect($message)
        ->title->toBe(__('filament-resources.database_notifications.territory_assigned_title', ['territory' => $assignment->territory->name]))
        ->body->toBe(__('filament-resources.database_notifications.territory_assigned_body', ['territory' => $assignment->territory->name]))
        ->icon->toBe(asset('icons/icon-192.png'))
        ->badge->toBe(asset('icons/badge-96.png'))
        ->data->toBe(['url' => TerritoryAssignmentResource::getUrl('view', ['record' => $assignment->id])]);
});

test('push notifications without actions open the app', function () {
    $message = (new TerritoryReturnedNotification(pushNotifiedAssignment()))
        ->toWebPush($this->user, new TestPushNotification)
        ->toArray();

    expect($message['data'])->toBe(['url' => route('dashboard')]);
});

test('the profile offers to enable push notifications only when they are configured', function (bool $isConfigured) {
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    if (! $isConfigured) {
        config(['webpush.vapid.public_key' => null]);
    }

    $response = $this->actingAs($this->user)->get(EditProfile::getUrl())->assertOk();

    $isConfigured
        ? $response->assertSee(__('profile.push_notifications.title'))->assertSee('BPublicKeyOfTheServer')->assertSee(__('profile.push_notifications.pop_up_tip'))
        : $response->assertDontSee(__('profile.push_notifications.title'));
})->with([
    'configured' => true,
    'not configured' => false,
]);

test('a full-screen dialog offers to enable push notifications after logging in, only when they can be', function (bool $isConfigured, bool $isImpersonating) {
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    if (! $isConfigured) {
        config(['webpush.vapid.public_key' => null]);
    }

    Impersonation::partialMock()->shouldReceive('isImpersonating')->andReturn($isImpersonating);

    $response = $this->actingAs($this->user)
        ->withSession([PushNotificationsPrompt::OFFER_SESSION_KEY => true])
        ->followingRedirects()
        ->get('/app')
        ->assertOk();

    $isConfigured && ! $isImpersonating
        ? $response->assertSee('app-push-notifications-prompt fixed inset-0', escape: false)
            ->assertSee('...pushSubscription(', escape: false)
            ->assertSee(__('pwa.notifications.title'))
            ->assertSee(__('pwa.notifications.action'))
            ->assertSee(__('pwa.notifications.dismiss'))
            ->assertSee(__('pwa.notifications.later'))
        : $response->assertDontSee('app-push-notifications-prompt', escape: false);
})->with([
    'configured' => [true, false],
    'not configured' => [false, false],
    'impersonating' => [true, true],
]);

test('the dialog is offered only on the first page after logging in', function () {
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    $this->user->update(['password' => Hash::make('a-password')]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $this->user->email, 'password' => 'a-password'])
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($this->user);

    $this->followingRedirects()->get('/app')->assertOk()->assertSee('app-push-notifications-prompt', escape: false);
    $this->followingRedirects()->get('/app')->assertOk()->assertDontSee('app-push-notifications-prompt', escape: false);
});

test('the dialog is not offered to users already logged in', function () {
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    $this->actingAs($this->user)
        ->followingRedirects()
        ->get('/app')
        ->assertOk()
        ->assertDontSee('app-push-notifications-prompt', escape: false);
});

test('the dialog takes turns with the offer to install the app', function () {
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    $this->actingAs($this->user)
        ->withSession([PushNotificationsPrompt::OFFER_SESSION_KEY => true])
        ->followingRedirects()
        ->get('/app')
        ->assertOk()
        ->assertSee("Alpine.store('fullScreenPrompt', { current: null })", escape: false)
        ->assertSee("name: 'pwa-install'", escape: false)
        ->assertSee("name: 'push-notifications'", escape: false)
        ->assertSee('Promise.all([this.refreshStatus(), this.waitForInstallOffer()])', escape: false);
});

test('the dialog does not show on the login page', function () {
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    $this->get('/app/login')->assertOk()->assertDontSee('app-push-notifications-prompt', escape: false);
});

test('users can enable push notifications on a device from the dialog', function () {
    $this->actingAs($this->user);

    Livewire::test(PushNotificationsPrompt::class)
        ->call('subscribe', $this->subscription)
        ->assertHasNoErrors();

    expect($this->user->pushSubscriptions()->sole()->endpoint)->toBe('https://push.example.com/send/abc123');
});

test('the dialog cannot enable push notifications while impersonating', function () {
    Impersonation::shouldReceive('isImpersonating')->andReturn(true);
    $this->actingAs($this->user);

    Livewire::test(PushNotificationsPrompt::class)
        ->call('subscribe', $this->subscription)
        ->assertNotFound();

    expect($this->user->pushSubscriptions()->exists())->toBeFalse();
});

test('the push subscription script is loaded in the panels', function () {
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    $this->actingAs($this->user)
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSee(FilamentAsset::getScriptSrc('push-subscription'), escape: false);

    expect(file_get_contents(resource_path('js/push-subscription.js')))
        ->toContain('window.pushSubscription = (publicKey) =>')
        ->toContain('this.$wire.subscribe(');
});

test('users can enable push notifications on a device', function () {
    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)
        ->call('subscribe', $this->subscription)
        ->assertHasNoErrors();

    expect($this->user->pushSubscriptions()->sole())
        ->endpoint->toBe('https://push.example.com/send/abc123')
        ->public_key->toBe('public-key-of-the-browser')
        ->auth_token->toBe('auth-secret');
});

test('the subscription of a device is updated, not duplicated', function () {
    $this->actingAs($this->user);
    subscribe($this->user, $this->subscription);

    Livewire::test(ProfilePushNotifications::class)
        ->call('subscribe', [...$this->subscription, 'keys' => ['p256dh' => 'renewed-key', 'auth' => 'renewed-secret']]);

    expect($this->user->pushSubscriptions()->sole())
        ->public_key->toBe('renewed-key')
        ->auth_token->toBe('renewed-secret');
});

test('a device moves to the user who enables push notifications on it', function () {
    $previousUser = User::factory()->create();
    subscribe($previousUser, $this->subscription);

    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)->call('subscribe', $this->subscription);

    expect($previousUser->pushSubscriptions()->exists())->toBeFalse()
        ->and($this->user->pushSubscriptions()->count())->toBe(1);
});

test('the device of another user cannot be taken over with its endpoint alone', function () {
    $owner = User::factory()->create();
    subscribe($owner, $this->subscription);

    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)
        ->call('subscribe', [...$this->subscription, 'keys' => ['p256dh' => 'guessed-key', 'auth' => 'guessed-secret']])
        ->assertHasErrors('endpoint');

    expect($owner->pushSubscriptions()->sole())->public_key->toBe('public-key-of-the-browser')
        ->and($this->user->pushSubscriptions()->exists())->toBeFalse();
});

test('invalid subscriptions are rejected', function (array $subscription) {
    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)
        ->call('subscribe', [...$this->subscription, ...$subscription])
        ->assertHasErrors();

    expect($this->user->pushSubscriptions()->exists())->toBeFalse();
})->with([
    'not HTTPS' => [['endpoint' => 'http://push.example.com/send/abc123']],
    'not a URL' => [['endpoint' => 'not a url']],
    'without keys' => [['keys' => []]],
    'unknown encoding' => [['contentEncoding' => 'gzip']],
]);

test('subscriptions are rejected when push notifications are not configured', function () {
    config(['webpush.vapid.private_key' => null]);
    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)
        ->call('subscribe', $this->subscription)
        ->assertNotFound();
});

test('users can disable push notifications only on their own devices', function () {
    $otherUser = User::factory()->create();
    subscribe($otherUser, $this->subscription);
    subscribe($this->user, [...$this->subscription, 'endpoint' => 'https://push.example.com/send/own']);

    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)
        ->call('unsubscribe', $this->subscription['endpoint'])
        ->call('unsubscribe', 'https://push.example.com/send/own');

    expect($this->user->pushSubscriptions()->exists())->toBeFalse()
        ->and($otherUser->pushSubscriptions()->exists())->toBeTrue();
});

test('users can check whether the subscription of the device is theirs', function () {
    subscribe(User::factory()->create(), $this->subscription);
    subscribe($this->user, [...$this->subscription, 'endpoint' => 'https://push.example.com/send/own']);

    $this->actingAs($this->user);

    $component = Livewire::test(ProfilePushNotifications::class);

    expect($component->instance()->ownsSubscription('https://push.example.com/send/own'))->toBeTrue()
        ->and($component->instance()->ownsSubscription($this->subscription['endpoint']))->toBeFalse();
});

test('the test push notification warns users without enabled devices', function () {
    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)
        ->call('sendTest')
        ->assertNotified(__('profile.push_notifications.test_no_devices'));

    Notification::assertNothingSent();
});

test('test push notifications are throttled', function () {
    subscribe($this->user, $this->subscription);
    $this->actingAs($this->user);

    $component = Livewire::test(ProfilePushNotifications::class);

    foreach (range(1, ProfilePushNotifications::TEST_NOTIFICATIONS_PER_MINUTE) as $attempt) {
        $component->call('sendTest')->assertNotified(__('profile.push_notifications.test_sent'));
    }

    $component->call('sendTest')->assertNotified(__('profile.push_notifications.test_too_many'));

    Notification::assertSentToTimes($this->user, TestPushNotification::class, ProfilePushNotifications::TEST_NOTIFICATIONS_PER_MINUTE);
});

test('the push notifications of the device stop when the user logs out', function (bool $isConfigured) {
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }

    if (! $isConfigured) {
        config(['webpush.vapid.public_key' => null]);
    }

    $response = $this->actingAs($this->user)->get(EditProfile::getUrl())->assertOk();

    $isConfigured
        ? $response->assertSee('form.dataset.pushUnsubscribed', escape: false)->assertSee(Filament::getLogoutUrl(), escape: false)
        : $response->assertDontSee('pushUnsubscribed', escape: false);
})->with([
    'configured' => true,
    'not configured' => false,
]);

test('push subscriptions use the default database connection, unless configured otherwise', function () {
    subscribe($this->user, $this->subscription);

    expect($this->user->pushSubscriptions()->sole()->getConnectionName())->toBe(config('database.default'));
});

test('users can send themselves a test push notification', function () {
    subscribe($this->user, $this->subscription);
    $this->actingAs($this->user);

    Livewire::test(ProfilePushNotifications::class)
        ->call('sendTest')
        ->assertNotified(__('profile.push_notifications.test_sent'));

    Notification::assertSentTo($this->user, TestPushNotification::class, function (TestPushNotification $notification, array $channels): bool {
        return $channels === [WebPushChannel::class];
    });
});

test('the subscriptions are deleted with the user', function () {
    subscribe($this->user, $this->subscription);

    $this->user->delete();

    expect($this->user->pushSubscriptions()->exists())->toBeFalse();
});

test('the service worker shows the push notifications and opens their page', function () {
    expect(file_get_contents(public_path('sw.js')))
        ->toContain("self.addEventListener('push'")
        ->toContain('self.registration.showNotification(title, options)')
        ->toContain('payload = { body: event.data?.text() }')
        ->toContain("self.addEventListener('notificationclick'")
        ->toContain('event.notification.data?.url');
});

test('the badge of the push notifications is a transparent silhouette, as Android shows only its alpha channel', function () {
    $badge = imagecreatefrompng(public_path('icons/badge-96.png'));

    expect(imagesx($badge))->toBe(96)
        ->and(imagecolorsforindex($badge, imagecolorat($badge, 0, 0))['alpha'])->toBe(127);
});
