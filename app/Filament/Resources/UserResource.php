<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-m-users';
    protected static ?string $tenantOwnershipRelationshipName = 'teams';
    
    public static function getModelLabel(): string
    {
        return 'utente';
    }
    public static function getPluralModelLabel(): string
    {
        return 'utenti';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Amministrazione';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dati personali')
                    ->description('Le informazioni di base dell\'utente.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        ]),
                Forms\Components\Section::make('Informazioni di accesso')
                    ->schema([
                        Forms\Components\Checkbox::make('can_login')
                            ->live()
                            ->label('Permetti accesso')
                            ->helperText('Seleziona per permettere all\'utente di accedere al sistema.'),
                        Forms\Components\TextInput::make('password')
                            ->disabled(fn (Forms\Get $get) => $get('can_login') === false) 
                            ->password()
                            ->required(fn (Forms\Get $get) => $get('can_login') === false)
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Email verified?')
                    ->default(false)
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('can_login')
                    ->label('Accesso abilitato?')
                    ->boolean()
                    ->sortable(),
                // Tables\Columns\IconColumn::make('two_factor_confirmed_at')
                //     ->label('2FA confirmed?')
                //     ->default(false)
                //     ->boolean()
                //     ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('can_login')
                    ->label('Accesso abilitato?')
                    ->placeholder('Tutti')
                    ->trueLabel('Abilitato')
                    ->falseLabel('Disabilitato'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
