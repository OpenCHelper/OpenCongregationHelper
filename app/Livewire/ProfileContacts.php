<?php

namespace App\Livewire;

use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\User;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Forms\Form;

use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists;
use Filament\Infolists\Infolist;

use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProfileContacts extends Component implements HasForms, HasTable, HasInfolists
{
    use InteractsWithForms, InteractsWithTable, InteractsWithInfolists;

    public ?array $data = [];
    public ?Contact $editingContact = null;

    public function mount()
    {
        $this->form->fill();
    }

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
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->actions([
                ViewAction::make()
                    ->infolist($this->getContactInfolist()),
                EditAction::make()
                    ->form($this->getFormSchema())
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
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('profile.contacts.contact_saved'))
                    ),
                DeleteAction::make()
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('profile.contacts.contact_deleted'))
                    ),
            ])
            ->headerActions([
                Action::make('create')
                    ->label(__('profile.contacts.add_contact'))
                    ->icon('heroicon-o-plus')
                    ->form($this->getFormSchema())
                    ->action(function (array $data): void {
                        // If this contact is set as primary, unset any other primary contacts
                        // if ($data['is_primary']) {
                        //     /** @var User $user */
                        //     $user = Auth::user();
                        //     $user->contacts()->update(['is_primary' => false]);
                        // }
                        
                        /** @var User $user */
                        $user = Auth::user();
                        $contact = $user->contacts()->create($data);

                        // Save the relationships from the form to the post after it is created.
                        $this->form->model($contact)->saveRelationships();
                    })
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('profile.contacts.contact_saved'))
                    ),
            ])
            ->emptyStateHeading(__('profile.contacts.no_contacts'))
            ->emptyStateDescription(__('profile.contacts.no_contacts_description'))
            ->emptyStateActions([
                Action::make('create')
                    ->label(__('profile.contacts.add_contact'))
                    ->icon('heroicon-o-plus')
                    ->form($this->getFormSchema())
                    ->action(function (array $data): void {
                        // If this contact is set as primary, unset any other primary contacts
                        // if ($data['is_primary']) {
                        //     /** @var User $user */
                        //     $user = Auth::user();
                        //     $user->contacts()->update(['is_primary' => false]);
                        // }
                        
                        /** @var User $user */
                        $user = Auth::user();
                        $contact = $user->contacts()->create($data);

                        // Save the relationships from the form to the post after it is created.
                        $this->form->model($contact)->saveRelationships();
                    })
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('profile.contacts.contact_saved'))
                    ),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return Contact::query()
            ->where('user_id', Auth::id())
            ->orderBy('is_primary', 'desc')
            ->orderBy('type');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema($this->getContactInfolist());
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('type')
                ->label(__('profile.contacts.type'))
                ->options(ContactType::class)
                ->required(),
            Forms\Components\Toggle::make('is_primary')
                ->label(__('profile.contacts.is_primary'))
                ->default(false),
            Forms\Components\TextInput::make('name')
                ->label(__('profile.contacts.name'))
                ->prefixIcon('heroicon-o-user')
                ->required(),
            Forms\Components\TextInput::make('phone_number')
                ->label(__('profile.contacts.phone_number'))
                ->prefixIcon('heroicon-o-phone')
                ->requiredWithout('address'),
            Forms\Components\TextInput::make('address')
                ->label(__('profile.contacts.address'))
                ->prefixIcon('heroicon-o-map-pin')
                ->requiredWithout('phone_number'),
            Forms\Components\Textarea::make('notes')
                ->label(__('profile.contacts.notes'))
                ->placeholder('Notes...')
                ->columnSpanFull(),
        ];
    }

    protected function getContactInfolist(): array
    {
        return [
            Infolists\Components\TextEntry::make('type')
                ->label(__('profile.contacts.type'))
                ->badge(),
            Infolists\Components\IconEntry::make('is_primary')
                ->boolean()
                ->label(__('profile.contacts.is_primary')),
            Infolists\Components\TextEntry::make('name')
                ->label(__('profile.contacts.name'))
                ->icon('heroicon-o-user'),
            Infolists\Components\TextEntry::make('phone_number')
                ->label(__('profile.contacts.phone_number'))
                ->icon('heroicon-o-phone')
                ->visible(fn (Contact $record) => !empty($record->phone_number))
                ->suffixAction(
                    Infolists\Components\Actions\Action::make('call')
                        ->icon('heroicon-o-phone-arrow-up-right')
                        ->label(__('profile.contacts.call'))
                        ->url(
                            fn (Contact $record): string => 'tel:' . $record->phone_number,
                            shouldOpenInNewTab: false
                        )
                ),
            Infolists\Components\TextEntry::make('address')
                ->label(__('profile.contacts.address'))
                ->icon('heroicon-o-map-pin')
                ->visible(fn (Contact $record) => !empty($record->address))
                ->suffixAction(
                    Infolists\Components\Actions\Action::make('open_maps')
                        ->icon('heroicon-o-map')
                        ->label(__('profile.contacts.open_maps'))
                        ->url(
                            fn (Contact $record): string => 'https://www.google.com/maps/search/' . urlencode($record->address),
                            shouldOpenInNewTab: true
                        )
                ),
            Infolists\Components\TextEntry::make('notes')
                ->label(__('profile.contacts.notes'))
                ->visible(fn (Contact $record) => !empty($record->notes))
                ->icon('heroicon-o-document-text')
                ->columnSpanFull(),
        ];
    }

    public function render()
    {
        return view('livewire.profile-contacts');
    }
}
