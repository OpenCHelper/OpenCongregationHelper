<?php

namespace App\Filament\Resources\Users;

use App\Enums\NavigationGroup;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use App\Filament\Resources\Users\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Pages\ManageUserAssignments;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use STS\FilamentImpersonate\Actions\Impersonate;
use Illuminate\Support\Facades\Auth;
use Override;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string | \BackedEnum | null $activeNavigationIcon = Heroicon::Users;
    protected static ?string $tenantOwnershipRelationshipName = 'teams';

    public static function getModelLabel(): string
    {
        return __('filament-resources.user.model_label');
    }
    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.user.plural_model_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Administration;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-resources.user.section_personal_data'))
                    ->description(__('filament-resources.user.section_personal_data_description'))
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('filament-resources.user.field_first_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label(__('filament-resources.user.field_last_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('filament-resources.user.field_email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?User $record) => $record?->isRootUserProtected())
                            ->helperText(fn (?User $record) => $record?->isRootUserProtected()
                                ? __('profile.root_user_protection.generic_field_disabled')
                                : null
                            ),
                        ]),
                Section::make(__('filament-resources.user.section_login_info'))
                    ->schema([
                        Checkbox::make('can_login')
                            ->live()
                            ->label(__('filament-resources.user.field_can_login'))
                            ->helperText(fn (?User $record) => $record?->isRootUserProtected()
                                ? __('profile.root_user_protection.generic_field_disabled')
                                : __('filament-resources.user.field_can_login_helper')
                            )
                            ->disabled(fn (?User $record) => $record?->isRootUserProtected()),
                        TextInput::make('password')
                            ->label(__('filament-resources.user.field_password'))
                            ->hiddenOn('view')
                            ->disabled(fn (Get $get, ?User $record) =>
                                $get('can_login') === false || $record?->isRootUserProtected()
                            )
                            ->password()
                            ->required(fn (Get $get, ?User $record) =>
                                $get('can_login') && !$record?->isRootUserProtected()
                            )
                            ->maxLength(255)
                            ->helperText(fn (?User $record) => $record?->isRootUserProtected()
                                ? __('profile.root_user_protection.password_change_not_allowed')
                                : null
                            ),
                        Select::make('roles')
                            ->label(__('filament-resources.user.field_roles'))
                            ->hidden(fn (Get $get) => $get('can_login') === false)
                            ->disabled(fn (Get $get, ?User $record) =>
                                $get('can_login') === false || $record?->isRootUserProtected()
                            )
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText(fn (?User $record) => $record?->isRootUserProtected()
                                ? __('profile.root_user_protection.generic_field_disabled')
                                : null
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): string => UserResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('first_name')
                    ->label(__('filament-resources.user.column_first_name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label(__('filament-resources.user.column_last_name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('filament-resources.user.column_email'))
                    ->copyable()
                    ->icon(Heroicon::Envelope)
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('filament-resources.user.column_role'))
                    ->badge()
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('email_verified_at')
                    ->label(__('filament-resources.user.column_email_verified'))
                    ->default(false)
                    ->boolean()
                    ->sortable(),
                IconColumn::make('can_login')
                    ->label(__('filament-resources.user.column_access_enabled'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-resources.user.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('filament-resources.user.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('can_login')
                    ->label(__('filament-resources.user.filter_access_enabled'))
                    ->placeholder(__('filament-resources.user.filter_access_enabled_all'))
                    ->trueLabel(__('filament-resources.user.filter_access_enabled_enabled'))
                    ->falseLabel(__('filament-resources.user.filter_access_enabled_disabled')),
            ])
            ->recordActions([
                Impersonate::make()
                    ->requiresConfirmation()
                    ->redirectTo('/'),
                EditAction::make()
                    ->disabled(function (User $record) {
                        $currentUser = Auth::user();
                        return $record->isRootUserProtected() &&
                               !($currentUser instanceof User && $currentUser->isRootUser());
                    })
                    ->tooltip(function (User $record) {
                        $currentUser = Auth::user();
                        return $record->isRootUserProtected() &&
                               !($currentUser instanceof User && $currentUser->isRootUser())
                            ? __('profile.root_user_protection.edit_action_disabled')
                            : null;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records) {
                            // Check if root user is in the selection
                            $hasRootUser = $records->contains(fn (User $record) => $record->isRootUser());

                            // Filter out root user before deletion (root user can never be deleted)
                            $recordsToDelete = $records->filter(fn (User $record) => !$record->isRootUser());
                            $deletedCount = 0;

                            if ($recordsToDelete->isNotEmpty()) {
                                $recordsToDelete->each->delete();
                                $deletedCount = $recordsToDelete->count();
                            }

                            // Provide appropriate feedback based on the situation
                            if ($hasRootUser && $deletedCount > 0) {
                                // Some users deleted, but root user was excluded
                                Notification::make()
                                    ->warning()
                                    ->title(__('profile.root_user_protection.bulk_delete_partial_success', ['deleted' => $deletedCount]))
                                    ->send();
                            } elseif ($hasRootUser && $deletedCount === 0) {
                                // Only root user was selected
                                Notification::make()
                                    ->danger()
                                    ->title(__('profile.root_user_protection.bulk_delete_only_root_selected'))
                                    ->send();
                            }
                            // If no root user was involved, let Filament show the default success message
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewUser::class,
            ManageUserAssignments::class,
        ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
            'assignments' => ManageUserAssignments::route('/{record}/assignments'),
        ];
    }
}
