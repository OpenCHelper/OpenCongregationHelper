<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\ContactType;
use App\Models\Contact;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';
    protected static ?string $title = 'Contatti';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('profile.contacts.section_title');
    }

    public static function getModelLabel(): string
    {
        return __('profile.contacts.section_title');
    }

    public function infolist(Infolists\Infolist $infolist): Infolists\Infolist
    {
        return $infolist
            ->schema([
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
            ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
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
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
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
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->defaultSort('is_primary', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
