<?php

use App\Enums\DepartmentKey;
use App\Enums\PublicationCategory;
use App\Filament\Resources\Publications\Pages\ManagePublications;
use App\Models\Department;
use App\Models\Publication;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // A member of the publications department, who can manage the list without a role
    $this->member = User::factory()->create();
    Department::for(DepartmentKey::Publications)->members()->attach($this->member);
    $this->actingAs($this->member);
});

test('the migrations create the periodicals, offered, with their code, category and renewal', function () {
    $publications = Publication::query()->ordered()->get();

    expect($publications->pluck('code')->all())->toBe(['w', 'wlp', 'wp', 'g', 'mwb', 'mwblp'])
        ->and($publications->pluck('category')->all())->toBe([
            PublicationCategory::Watchtower,
            PublicationCategory::Watchtower,
            PublicationCategory::Watchtower,
            PublicationCategory::Awake,
            PublicationCategory::MeetingWorkbooks,
            PublicationCategory::MeetingWorkbooks,
        ])
        ->and($publications->pluck('renewal_months')->all())->toBe([1, 1, null, null, 2, 2])
        ->and($publications->every(fn (Publication $publication): bool => $publication->is_active))->toBeTrue();
});

test('the names of the periodicals follow the language of the user, while the code is the same', function (string $locale, string $watchtower, string $awake) {
    App::setLocale($locale);

    $publications = Publication::query()->ordered()->get()->keyBy('code');

    expect($publications['w']->name)->toBe($watchtower)
        ->and($publications['g']->name)->toBe($awake)
        ->and($publications['w']->getShortLabel())->toBe('w')
        ->and(Publication::offered()->pluck('name')->all())->toContain($watchtower, $awake);
})->with([
    'English' => ['en', 'The Watchtower', 'Awake!'],
    'Italian' => ['it', 'La Torre di Guardia', 'Svegliatevi!'],
    'Spanish' => ['es', 'La Atalaya', '¡Despertad!'],
]);

test('every language of the application has the names of the periodicals', function () {
    $locales = array_keys(config('app.available_locales'));

    Publication::query()->each(fn (Publication $publication) => expect(array_keys($publication->getTranslations('name')))
        ->toEqualCanonicalizing($locales));
});

test('a publication named in a single language shows that name in the others too', function () {
    App::setLocale('it');
    $publication = Publication::factory()->create(['name' => 'Vivi felice per sempre!']);

    App::setLocale('en');

    expect($publication->fresh()->name)->toBe('Vivi felice per sempre!');
});

test('the form edits the name in every language, the one of the user first and required', function () {
    App::setLocale('it');
    $watchtower = Publication::query()->where('code', 'w')->sole();

    Livewire::test(ManagePublications::class)
        ->mountAction(TestAction::make('edit')->table($watchtower))
        ->assertSchemaStateSet([
            'name' => ['en' => 'The Watchtower', 'it' => 'La Torre di Guardia', 'es' => 'La Atalaya'],
            'code' => 'w',
        ])
        ->fillForm(['name' => ['en' => 'The Watchtower (study edition)', 'it' => '', 'es' => '']])
        ->callMountedAction()
        ->assertHasFormErrors(['name.it' => 'required'])
        ->fillForm(['name' => ['en' => 'The Watchtower (study edition)', 'it' => 'La Torre di Guardia (edizione per lo studio)', 'es' => '']])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    // The language left empty falls back to another one
    expect($watchtower->fresh()->getTranslations('name'))->toEqual([
        'en' => 'The Watchtower (study edition)',
        'it' => 'La Torre di Guardia (edizione per lo studio)',
    ]);
});

test('the publications are found by their name in any language', function () {
    App::setLocale('it');

    Livewire::test(ManagePublications::class)
        ->searchTable('Atalaya')
        ->assertCanSeeTableRecords(Publication::query()->whereIn('code', ['w', 'wlp', 'wp'])->get())
        ->assertCanNotSeeTableRecords(Publication::query()->whereIn('code', ['g', 'mwb', 'mwblp'])->get());
});

test('a periodical can be deleted like any other publication, and is not created again', function () {
    $awake = Publication::query()->where('code', 'g')->sole();

    Livewire::test(ManagePublications::class)
        ->callAction(TestAction::make('delete')->table($awake))
        ->assertHasNoFormErrors();

    $this->artisan('migrate')->assertSuccessful();

    expect(Publication::query()->where('code', 'g')->exists())->toBeFalse()
        ->and(Publication::query()->count())->toBe(5);
});
