<?php

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Enums\PublicationCategory;
use App\Filament\Resources\Publications\Pages\ManagePublications;
use App\Models\Department;
use App\Models\Publication;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // An overseer of the publications department, who can fully manage the list without a role
    $this->member = User::factory()->create();
    Department::for(DepartmentKey::Publications)->members()->attach($this->member, ['role' => DepartmentRole::Overseer]);
    $this->actingAs($this->member);
});

test('the migrations create the Bible, the periodicals and the publications in print since 2015, offered', function () {
    $publications = Publication::query()->ordered()->get();

    expect($publications->pluck('code')->take(9)->all())->toBe(['nwt', 'nwtls', 'nwtpkt', 'nwtsty', 'w', 'wlp', 'wp', 'g', 'mwb'])
        ->and($publications->pluck('renewal_months', 'code')->only(['nwt', 'nwtls', 'nwtpkt', 'w', 'wlp', 'wp', 'g', 'mwb'])->all())
        ->toBe(['nwt' => null, 'nwtls' => null, 'nwtpkt' => null, 'w' => 1, 'wlp' => 1, 'wp' => null, 'g' => null, 'mwb' => 2])
        ->and($publications->countBy(fn (Publication $publication): string => $publication->category->value)->all())->toBe([
            'bibles' => 4,
            'watchtower' => 3,
            'awake' => 1,
            'meeting_workbooks' => 1,
            'books' => 14,
            'brochures_and_booklets' => 7,
        ])
        // Only the periodicals renew
        ->and($publications->filter->isRenewable()->pluck('code')->all())->toBe(['w', 'wlp', 'mwb'])
        ->and($publications->every(fn (Publication $publication): bool => $publication->is_active))->toBeTrue()
        ->and($publications->pluck('code')->duplicates()->all())->toBe([]);
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
        ->assertCanNotSeeTableRecords(Publication::query()->whereIn('code', ['nwt', 'g', 'mwb'])->get());
});

test('the search ignores the case, and finds the wildcards of the database as they are', function (string $search) {
    App::setLocale('it');

    Livewire::test(ManagePublications::class)
        ->searchTable($search)
        ->assertCanSeeTableRecords(Publication::query()->whereIn('code', ['w', 'wlp', 'wp'])->get())
        ->assertCanNotSeeTableRecords(Publication::query()->whereIn('code', ['nwt', 'g', 'mwb'])->get());
})->with(['lowercase' => 'atalaya', 'uppercase' => 'ATALAYA', 'mixed' => 'torre DI guardia']);

test('the wildcards of the database typed in the search are not wildcards', function () {
    $percent = Publication::factory()->create(['name' => ['en' => '100% Bible'], 'code' => 'pct']);
    $underscore = Publication::factory()->create(['name' => ['en' => 'snake_case'], 'code' => 'snk']);
    $other = Publication::factory()->create(['name' => ['en' => 'Snake case'], 'code' => 'oth']);

    Livewire::test(ManagePublications::class)
        ->searchTable('%')
        ->assertCanSeeTableRecords([$percent])
        ->assertCanNotSeeTableRecords([$underscore, $other])
        ->searchTable('e_c')
        ->assertCanSeeTableRecords([$underscore])
        ->assertCanNotSeeTableRecords([$percent, $other])
        ->searchTable('!')
        ->assertCanNotSeeTableRecords([$percent, $underscore, $other]);
});

test('a periodical can be deleted like any other publication, and is not created again', function () {
    $awake = Publication::query()->where('code', 'g')->sole();

    Livewire::test(ManagePublications::class)
        ->callAction(TestAction::make('delete')->table($awake))
        ->assertHasNoFormErrors();

    $this->artisan('migrate')->assertSuccessful();

    expect(Publication::query()->where('code', 'g')->exists())->toBeFalse()
        ->and(Publication::query()->count())->toBe(29);
});

test('each language of the name shows the example in that language', function () {
    App::setLocale('it');

    Livewire::test(ManagePublications::class)
        ->mountAction(TestAction::make('create'))
        ->assertFormFieldExists('name.it', fn (TextInput $field): bool => $field->getPlaceholder() === 'es. Svegliatevi!')
        ->assertFormFieldExists('name.en', fn (TextInput $field): bool => $field->getPlaceholder() === 'e.g. Awake!')
        ->assertFormFieldExists('name.es', fn (TextInput $field): bool => $field->getPlaceholder() === 'p. ej. ¡Despertad!');
});

test('the code of a publication is required and unique', function () {
    Livewire::test(ManagePublications::class)
        ->mountAction(TestAction::make('create'))
        ->fillForm([
            'name' => [app()->getLocale() => 'Enjoy Life Forever!'],
            'category' => PublicationCategory::Books->value,
            'code' => '',
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['code' => 'required'])
        ->fillForm(['code' => 'w'])
        ->callMountedAction()
        ->assertHasFormErrors(['code' => 'unique']);

    expect(Publication::query()->count())->toBe(30);
});

test('the renewal is shown only for the publications received regularly', function () {
    $watchtower = Publication::query()->where('code', 'w')->sole();
    $awake = Publication::query()->where('code', 'g')->sole();

    Livewire::test(ManagePublications::class)
        ->mountAction(TestAction::make('edit')->table($awake))
        ->assertSchemaStateSet(['is_renewable' => false])
        ->assertSchemaComponentHidden('renewal_months', 'mountedActionSchema0')
        // Turned on, it starts from every month and must be filled in
        ->fillForm(['is_renewable' => true])
        ->assertSchemaComponentVisible('renewal_months', 'mountedActionSchema0')
        ->assertSchemaStateSet(['renewal_months' => 1])
        ->fillForm(['renewal_months' => null])
        ->callMountedAction()
        ->assertHasFormErrors(['renewal_months' => 'required'])
        ->fillForm(['renewal_months' => 4])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($awake->fresh()->renewal_months)->toBe(4);

    // Turned off, the renewal is stopped
    Livewire::test(ManagePublications::class)
        ->mountAction(TestAction::make('edit')->table($watchtower))
        ->assertSchemaStateSet(['is_renewable' => true, 'renewal_months' => 1])
        ->fillForm(['is_renewable' => false])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($watchtower->fresh()->renewal_months)->toBeNull();
});

test('a new publication is requested once unless it is received regularly', function () {
    Livewire::test(ManagePublications::class)
        ->callAction(TestAction::make('create'), [
            'name' => [app()->getLocale() => 'Enjoy Life Forever!'],
            'category' => PublicationCategory::Books->value,
            'code' => 'abc',
        ])
        ->assertHasNoFormErrors();

    expect(Publication::query()->where('code', 'abc')->sole()->renewal_months)->toBeNull();
});

test('the DVDs are listed after the printed publications, before the other ones', function () {
    $other = Publication::factory()->create(['category' => PublicationCategory::Other, 'sort' => 0]);
    $dvd = Publication::factory()->create(['category' => PublicationCategory::Dvds, 'sort' => 99]);
    $manual = Publication::factory()->create(['category' => PublicationCategory::ManualsAndGuidelines, 'sort' => 99]);

    expect(Publication::query()->ordered()->pluck('id')->take(-3)->values()->all())->toBe([$manual->id, $dvd->id, $other->id])
        ->and(PublicationCategory::Dvds->getLabel())->toBe('DVD');
});

test('the publications can be offered or no longer offered in bulk', function () {
    $books = Publication::query()->where('category', PublicationCategory::Books)->get();

    Livewire::test(ManagePublications::class)
        ->assertSee(__('filament-resources.publication.list_subheading'))
        ->selectTableRecords($books)
        ->mountAction(TestAction::make('stop_offering')->table()->bulk())
        ->assertMountedActionModalSee(__('filament-resources.publication.stop_offering_description'))
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Publication::query()->where('category', PublicationCategory::Books)->where('is_active', true)->exists())->toBeFalse()
        ->and(Publication::query()->where('code', 'w')->sole()->is_active)->toBeTrue();

    Livewire::test(ManagePublications::class)
        ->selectTableRecords($books->take(2))
        ->callAction(TestAction::make('offer')->table()->bulk());

    expect(Publication::query()->where('category', PublicationCategory::Books)->where('is_active', true)->pluck('id')->all())
        ->toEqualCanonicalizing($books->take(2)->modelKeys());
});
