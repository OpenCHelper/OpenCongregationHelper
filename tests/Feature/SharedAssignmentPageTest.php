<?php

use App\Http\Middleware\PreventSearchIndexing;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);

    $territory = Territory::factory()
        ->for(TerritoryZone::factory(), 'territoryZone')
        ->create(['name' => 'Shared territory', 'notes' => 'Private territory notes']);

    $this->assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->sharedUrl = $this->assignment->share();

    // Sharing loads the settings: forget them, so that they follow the configuration set by each test
    app()->forgetScopedInstances();
});

test('shared assignment pages render with the built frontend assets', function () {
    $this->get('/guest/shared-assignment/'.$this->sharedUrl->uid)
        ->assertOk();
})->skip(fn (): bool => ! frontendAssetsAreAvailable(), 'The frontend assets are not built, run `npm run build`.');

test('shared assignment pages hide personal data by default', function () {
    $this->withoutVite();
    config(['app.enable_shared_pii' => false]);

    $this->get('/guest/shared-assignment/'.$this->sharedUrl->uid)
        ->assertOk()
        ->assertSee('Shared territory')
        ->assertDontSee('Mario Rossi')
        ->assertDontSee('Private territory notes');
});

test('shared assignment pages show personal data when enabled', function () {
    $this->withoutVite();
    config(['app.enable_shared_pii' => true]);

    $this->get('/guest/shared-assignment/'.$this->sharedUrl->uid)
        ->assertOk()
        ->assertSee('Mario Rossi')
        ->assertSee('Private territory notes');
});

test('shared assignment pages ask search engines not to index them', function () {
    $this->withoutVite();

    $this->get('/guest/shared-assignment/'.$this->sharedUrl->uid)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', PreventSearchIndexing::DIRECTIVES)
        ->assertSee('<meta name="robots" content="'.PreventSearchIndexing::DIRECTIVES.'">', escape: false);
});

test('shared links ask search engines not to index them', function () {
    $this->get(route('share.redirect', $this->sharedUrl->uid))
        ->assertRedirect()
        ->assertHeader('X-Robots-Tag', PreventSearchIndexing::DIRECTIVES);
});

test('the crawlers can fetch the shared pages, so that they read that they must not index them', function () {
    $rules = collect(file(public_path('robots.txt'), FILE_IGNORE_NEW_LINES))
        ->reject(fn (string $line): bool => str_starts_with($line, '#'))
        ->filter(fn (string $line): bool => str_starts_with(strtolower($line), 'disallow:'))
        ->map(fn (string $line): string => trim(substr($line, strlen('disallow:'))));

    expect($rules->filter())->toBeEmpty();
});

test('the pages of the application can still be indexed', function () {
    $this->get('/')->assertHeaderMissing('X-Robots-Tag');
});
