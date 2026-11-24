<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\ContactType;
use App\Models\Contact;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('profile.contacts.section_title');
    }

    public static function getModelLabel(): string
    {
        return __('profile.contacts.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('profile.contacts.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
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
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->placeholder(__('profile.contacts.notes_placeholder'))
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('profile.contacts.type'))
                    ->badge(),
                TextColumn::make('name')->label(__('profile.contacts.name')),
                IconColumn::make('is_primary')
                    ->label(__('profile.contacts.is_primary'))
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedStar)
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->defaultSort('is_primary', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
