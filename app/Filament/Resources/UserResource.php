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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\FontFamily;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-m-users';
    protected static ?string $tenantOwnershipRelationshipName = 'teams';
    
    public static function getModelLabel(): string
    {
        return __('filament-resources.user.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.user.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-resources.user.navigation_group');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament-resources.user.section_personal_data'))
                    ->description(__('filament-resources.user.section_personal_data_description'))
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label(__('filament-resources.user.field_first_name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->label(__('filament-resources.user.field_last_name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label(__('filament-resources.user.field_email'))
                            ->email()
                            ->required()
                            ->maxLength(255),
                        ]),
                Forms\Components\Section::make(__('filament-resources.user.section_login_info'))
                    ->schema([
                        Forms\Components\Checkbox::make('can_login')
                            ->live()
                            ->label(__('filament-resources.user.field_can_login'))
                            ->helperText(__('filament-resources.user.field_can_login_helper')),
                        Forms\Components\TextInput::make('password')
                            ->label(__('filament-resources.user.field_password'))
                            ->hiddenOn('view')
                            ->disabled(fn (Forms\Get $get) => $get('can_login') === false)
                            ->password()
                            ->required(fn (Forms\Get $get) => $get('can_login'))
                            ->maxLength(255),
                        Forms\Components\Select::make('roles')
                            ->label(__('filament-resources.user.field_roles'))
                            ->hidden(fn (Forms\Get $get) => $get('can_login') === false)
                            ->disabled(fn (Forms\Get $get) => $get('can_login') === false)
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): string => UserResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label(__('filament-resources.user.column_first_name'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label(__('filament-resources.user.column_last_name'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('filament-resources.user.column_email'))
                    ->copyable()
                    ->icon('heroicon-m-envelope')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('filament-resources.user.column_role'))
                    ->badge()
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label(__('filament-resources.user.column_email_verified'))
                    ->default(false)
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('can_login')
                    ->label(__('filament-resources.user.column_access_enabled'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-resources.user.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament-resources.user.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('can_login')
                    ->label(__('filament-resources.user.filter_access_enabled'))
                    ->placeholder(__('filament-resources.user.filter_access_enabled_all'))
                    ->trueLabel(__('filament-resources.user.filter_access_enabled_enabled'))
                    ->falseLabel(__('filament-resources.user.filter_access_enabled_disabled')),
            ])
            ->actions([
                Impersonate::make()
                    ->requiresConfirmation()
                    ->redirectTo('/'),
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

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewUser::class,
            Pages\ManageUserAssignments::class,
        ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'assignments' => Pages\ManageUserAssignments::route('/{record}/assignments'),
        ];
    }
}
