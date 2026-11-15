<?php

use App\Enums\ContactType;
use App\Livewire\ProfileContacts;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs($this->user = User::factory()->create());
});

test('users only see their own contacts', function () {
    $ownContact = $this->user->contacts()->create(['type' => ContactType::EMERGENCY, 'name' => 'Own contact', 'phone_number' => '123']);
    $otherContact = User::factory()->create()->contacts()->create(['type' => ContactType::EMERGENCY, 'name' => 'Other contact', 'phone_number' => '456']);

    Livewire::test(ProfileContacts::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$ownContact])
        ->assertCanNotSeeTableRecords([$otherContact]);
});

test('users can add a contact', function (bool $hasContacts) {
    if ($hasContacts) {
        $this->user->contacts()->create(['type' => ContactType::PERSONAL, 'name' => 'Existing contact', 'phone_number' => '123']);
    }

    Livewire::test(ProfileContacts::class)
        ->callAction(TestAction::make('create')->table(), [
            'type' => ContactType::EMERGENCY->value,
            'name' => 'Mario Rossi',
            'phone_number' => '+39 333 1234567',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified(__('profile.contacts.contact_saved'));

    expect($this->user->contacts()->where('name', 'Mario Rossi')->sole())
        ->type->toBe(ContactType::EMERGENCY)
        ->phone_number->toBe('+39 333 1234567');
})->with([
    'from the empty state' => false,
    'from the table header' => true,
]);

test('a contact needs a phone number or an address', function () {
    Livewire::test(ProfileContacts::class)
        ->callAction(TestAction::make('create')->table(), [
            'type' => ContactType::EMERGENCY->value,
            'name' => 'Mario Rossi',
        ])
        ->assertHasFormErrors(['phone_number', 'address']);

    expect($this->user->contacts()->exists())->toBeFalse();
});

test('adding a primary contact unsets the previous primary contact', function () {
    $previousPrimary = $this->user->contacts()->create(['type' => ContactType::PERSONAL, 'name' => 'Previous', 'phone_number' => '123', 'is_primary' => true]);

    Livewire::test(ProfileContacts::class)
        ->callAction(TestAction::make('create')->table(), [
            'type' => ContactType::EMERGENCY->value,
            'name' => 'New primary',
            'phone_number' => '456',
            'is_primary' => true,
        ])
        ->assertHasNoFormErrors();

    expect($this->user->contacts()->where('name', 'New primary')->sole()->is_primary)->toBeTrue()
        ->and($previousPrimary->refresh()->is_primary)->toBeFalse();
});

test('making a contact primary unsets the previous primary contact', function () {
    $previousPrimary = $this->user->contacts()->create(['type' => ContactType::PERSONAL, 'name' => 'Previous', 'phone_number' => '123', 'is_primary' => true]);
    $contact = $this->user->contacts()->create(['type' => ContactType::EMERGENCY, 'name' => 'New primary', 'phone_number' => '456']);

    Livewire::test(ProfileContacts::class)
        ->callAction(TestAction::make('edit')->table($contact), ['is_primary' => true])
        ->assertHasNoFormErrors();

    expect($contact->refresh()->is_primary)->toBeTrue()
        ->and($previousPrimary->refresh()->is_primary)->toBeFalse();
});
