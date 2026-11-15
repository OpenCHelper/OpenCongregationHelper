<?php

namespace App\Livewire;

use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProfileContacts extends Component implements HasActions, HasForms, HasInfolists, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms, InteractsWithInfolists, InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->recordAction('view')
            ->columns([
                TextColumn::make('type')
                    ->label(__('profile.contacts.type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('profile.contacts.name'))
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_primary')
                    ->label(__('profile.contacts.primary'))
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedStar)
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema($this->getContactInfolist()),
                EditAction::make()
                    ->schema($this->getFormSchema())
                    ->using(function (array $data, Contact $record): Contact {
                        // If this contact is set as primary, unset any other primary contacts
                        if ($data['is_primary']) {
                            /** @var User $user */
                            $user = Auth::user();
                            $user->contacts()
                                ->where('id', '!=', $record->id)
                                ->update(['is_primary' => false]);
                        }

                        $record->update($data);

                        return $record;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('profile.contacts.contact_saved'))
                    ),
                DeleteAction::make()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('profile.contacts.contact_deleted'))
                    ),
            ])
            ->headerActions([
                $this->getCreateAction(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading(__('profile.contacts.no_contacts'))
            ->emptyStateDescription(__('profile.contacts.no_contacts_description'))
            ->emptyStateActions([
                $this->getCreateAction(),
            ]);
    }

    /**
     * Action to add a contact, shown both in the header of the table and in its empty state.
     */
    protected function getCreateAction(): Action
    {
        return Action::make('create')
            ->label(__('profile.contacts.add_contact'))
            ->icon(Heroicon::Plus)
            ->schema($this->getFormSchema())
            ->action(function (array $data, Schema $schema): void {
                /** @var User $user */
                $user = Auth::user();

                // A new primary contact replaces the previous one, as when editing a contact.
                if ($data['is_primary'] ?? false) {
                    $user->contacts()->update(['is_primary' => false]);
                }

                $contact = $user->contacts()->create($data);

                // Save the relationships of the action form to the created contact
                $schema->model($contact)->saveRelationships();
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title(__('profile.contacts.contact_saved'))
            );
    }

    protected function getTableQuery(): Builder
    {
        return Contact::query()
            ->where('user_id', Auth::id())
            ->orderBy('is_primary', 'desc')
            ->orderBy('type');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components($this->getContactInfolist());
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('type')
                ->label(__('profile.contacts.type'))
                ->options(ContactType::class)
                ->required(),
            Toggle::make('is_primary')
                ->label(__('profile.contacts.is_primary'))
                ->default(false),
            TextInput::make('name')
                ->label(__('profile.contacts.name'))
                ->prefixIcon(Heroicon::OutlinedUser)
                ->required(),
            TextInput::make('phone_number')
                ->label(__('profile.contacts.phone_number'))
                ->prefixIcon(Heroicon::OutlinedPhone)
                ->requiredWithout('address'),
            TextInput::make('address')
                ->label(__('profile.contacts.address'))
                ->prefixIcon(Heroicon::OutlinedMapPin)
                ->requiredWithout('phone_number'),
            Textarea::make('notes')
                ->label(__('profile.contacts.notes'))
                ->columnSpanFull(),
        ];
    }

    protected function getContactInfolist(): array
    {
        return [
            TextEntry::make('type')
                ->label(__('profile.contacts.type'))
                ->badge(),
            IconEntry::make('is_primary')
                ->boolean()
                ->label(__('profile.contacts.is_primary')),
            TextEntry::make('name')
                ->label(__('profile.contacts.name'))
                ->icon(Heroicon::OutlinedUser),
            TextEntry::make('phone_number')
                ->label(__('profile.contacts.phone_number'))
                ->icon(Heroicon::OutlinedPhone)
                ->visible(fn (Contact $record) => ! empty($record->phone_number))
                ->suffixAction(
                    Action::make('call')
                        ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                        ->label(__('profile.contacts.call'))
                        ->url(
                            fn (Contact $record): string => 'tel:'.$record->phone_number,
                            shouldOpenInNewTab: false
                        )
                ),
            TextEntry::make('address')
                ->label(__('profile.contacts.address'))
                ->icon(Heroicon::OutlinedMapPin)
                ->visible(fn (Contact $record) => ! empty($record->address))
                ->suffixAction(
                    Action::make('open_maps')
                        ->icon(Heroicon::OutlinedMap)
                        ->label(__('profile.contacts.open_maps'))
                        ->url(
                            fn (Contact $record): string => 'https://www.google.com/maps/search/'.urlencode($record->address),
                            shouldOpenInNewTab: true
                        )
                ),
            TextEntry::make('notes')
                ->label(__('profile.contacts.notes'))
                ->visible(fn (Contact $record) => ! empty($record->notes))
                ->icon(Heroicon::OutlinedDocumentText)
                ->columnSpanFull(),
        ];
    }

    public function render()
    {
        return view('livewire.profile-contacts');
    }
}
