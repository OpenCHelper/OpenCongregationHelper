<?php

use App\Enums\ContactType;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\ContactsRelationManager;
use App\Models\Contact;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // The root user, who has a special treatment.
    User::factory()->create(['id' => 1]);

    $this->user = User::factory()->create();
});

function createContact(User $user, string $name, bool $isPrimary = false): Contact
{
    return $user->contacts()->create(['type' => ContactType::PERSONAL, 'name' => $name, 'phone_number' => '123', 'is_primary' => $isPrimary]);
}

test('a contact saved as primary replaces the previous primary contact of the user only', function () {
    $previousPrimary = createContact($this->user, 'Previous', isPrimary: true);
    $otherUsersPrimary = createContact(User::factory()->create(), 'Other user', isPrimary: true);

    $newPrimary = createContact($this->user, 'New', isPrimary: true);

    expect($previousPrimary->refresh()->is_primary)->toBeFalse()
        ->and($otherUsersPrimary->refresh()->is_primary)->toBeTrue()
        ->and($this->user->primaryContact->is($newPrimary))->toBeTrue();

    $previousPrimary->update(['is_primary' => true]);

    expect($newPrimary->refresh()->is_primary)->toBeFalse()
        ->and($this->user->contacts()->where('is_primary', true)->count())->toBe(1);
});

test('saving a contact that is not primary keeps the primary contact', function () {
    $primary = createContact($this->user, 'Primary', isPrimary: true);

    createContact($this->user, 'Secondary')->update(['name' => 'Renamed']);

    expect($primary->refresh()->is_primary)->toBeTrue();
});

test('the primary contact of a user is a single contact', function () {
    expect($this->user->primaryContact)->toBeNull();

    $primary = createContact($this->user, 'Primary', isPrimary: true);
    createContact($this->user, 'Secondary');

    expect($this->user->fresh()->primaryContact->is($primary))->toBeTrue();
});

test('administrators setting a primary contact replace the previous one', function () {
    Filament::setCurrentPanel('app');
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    $this->actingAs($admin);

    $previousPrimary = createContact($this->user, 'Previous', isPrimary: true);
    $contact = createContact($this->user, 'Secondary');

    Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $this->user, 'pageClass' => EditUser::class])
        ->callAction(TestAction::make('edit')->table($contact), ['is_primary' => true])
        ->assertHasNoFormErrors();

    expect($contact->refresh()->is_primary)->toBeTrue()
        ->and($previousPrimary->refresh()->is_primary)->toBeFalse();

    Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $this->user, 'pageClass' => EditUser::class])
        ->callAction(TestAction::make('create')->table(), [
            'type' => ContactType::EMERGENCY->value,
            'name' => 'New primary',
            'phone_number' => '456',
            'is_primary' => true,
        ])
        ->assertHasNoFormErrors();

    expect($this->user->contacts()->where('is_primary', true)->sole()->name)->toBe('New primary');
});
