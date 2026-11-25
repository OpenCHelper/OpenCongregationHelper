<?php

namespace App\Filament\Resources\Users;

use App\Enums\DepartmentKey;
use App\Enums\NavigationGroup;
use App\Filament\Resources\Users\Actions\AddToDepartmentBulkAction;
use App\Filament\Resources\Users\Actions\DeleteUsersBulkAction;
use App\Filament\Resources\Users\Actions\ResendInvitationAction;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ManageUserAssignments;
use App\Filament\Resources\Users\Pages\ManageUserPublicationRequests;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ContactsRelationManager;
use App\Models\Department;
use App\Models\User;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Override;
use STS\FilamentImpersonate\Actions\Impersonate;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Users;

    protected static ?string $tenantOwnershipRelationshipName = 'teams';

    public static function getModelLabel(): string
    {
        return __('filament-resources.user.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.user.plural_model_label');
    }

    /**
     * Users are titled by their full name (e.g. in the title and breadcrumbs of their pages), which is not a column: it
     * is not the `$recordTitleAttribute`, that the global search would look for in the database.
     */
    #[Override]
    public static function hasRecordTitle(): bool
    {
        return true;
    }

    #[Override]
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof User ? $record->getFullName() : static::getModelLabel();
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Administration;
    }

    /**
     * Only super administrators can assign roles, to prevent privilege escalation
     * (e.g. a user granting themselves the `super_admin` role).
     */
    public static function canManageRoles(): bool
    {
        $currentUser = Auth::user();

        return $currentUser instanceof User && $currentUser->hasRole('super_admin');
    }

    /**
     * Whether saving the form sends the user an invitation to choose their password: when a user is given access and
     * has no password yet, i.e. when creating one, or when allowing access to a user who never had it, and when the
     * email of a user who has not chosen their password yet is corrected (the previous invitation went to the old
     * address). Administrators never set passwords themselves.
     */
    public static function willSendInvitation(bool $canLogin, ?User $record, ?string $email = null): bool
    {
        if (! $canLogin) {
            return false;
        }

        if ($record === null) {
            return true;
        }

        return blank($record->password)
            && (! $record->can_login || ($email !== null && $email !== $record->email));
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
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?User $record) => $record?->isRootUserProtected())
                            ->helperText(fn (?User $record) => match (true) {
                                (bool) $record?->isRootUserProtected() => __('profile.root_user_protection.generic_field_disabled'),
                                (bool) $record?->isAwaitingInvitation() => __('filament-resources.user.field_email_invitation_helper'),
                                default => null,
                            }),
                    ]),
                Section::make(__('filament-resources.user.section_login_info'))
                    ->schema([
                        Checkbox::make('can_login')
                            ->live()
                            ->label(__('filament-resources.user.field_can_login'))
                            ->helperText(fn (Get $get, ?User $record) => match (true) {
                                (bool) $record?->isRootUserProtected() => __('profile.root_user_protection.generic_field_disabled'),
                                static::willSendInvitation((bool) $get('can_login'), $record) => __('filament-resources.user.field_can_login_helper')
                                    .' '.__('filament-resources.user.field_can_login_invitation_helper'),
                                default => __('filament-resources.user.field_can_login_helper'),
                            })
                            ->disabled(fn (?User $record) => $record?->isRootUserProtected()),
                        Select::make('roles')
                            ->label(__('filament-resources.user.field_roles'))
                            ->hidden(fn (Get $get) => $get('can_login') === false)
                            ->disabled(fn (Get $get, ?User $record) => $get('can_login') === false
                                || $record?->isRootUserProtected()
                                || ! static::canManageRoles()
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
            ->stackedOnMobile()
            ->columns([
                // On phones, each user is a card (see `user-card.blade.php`) that shows the columns hidden below
                ViewColumn::make('card')
                    ->label(__('filament-resources.user.column_user'))
                    ->view('filament.tables.columns.user-card')
                    ->sortable(['last_name', 'first_name'])
                    ->extraCellAttributes(['class' => 'fi-ta-cell-without-stacked-label'])
                    ->hiddenFrom('sm'),
                TextColumn::make('first_name')
                    ->label(__('filament-resources.user.column_first_name'))
                    ->sortable()
                    ->searchable()
                    ->visibleFrom('sm'),
                TextColumn::make('last_name')
                    ->label(__('filament-resources.user.column_last_name'))
                    ->sortable()
                    ->searchable()
                    ->visibleFrom('sm'),
                TextColumn::make('email')
                    ->label(__('filament-resources.user.column_email'))
                    ->copyable()
                    ->icon(Heroicon::Envelope)
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->visibleFrom('sm')
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label(__('filament-resources.user.column_role'))
                    ->badge()
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $roles = Relation::noConstraints(fn (): BelongsToMany => $query->getModel()->roles());
                        $roleName = $roles->getRelated()->qualifyColumn('name');

                        // Users with several roles are sorted by the first one in alphabetical order
                        $firstRoleName = $roles->getRelationExistenceQuery($roles->getRelated()->newQuery(), $query, [$roleName])
                            ->orderBy($roleName)
                            ->limit(1);

                        return $query->orderBy($firstRoleName->toBase(), $direction);
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('sm'),
                TextColumn::make('departments')
                    ->label(__('filament-resources.user.column_departments'))
                    ->state(fn (User $record): array => $record->departments->map(fn (Department $department): DepartmentKey => $department->key)->all())
                    ->formatStateUsing(fn (DepartmentKey $state): string => $state->getLabel())
                    ->color(fn (DepartmentKey $state): string => $state->getColor())
                    ->badge()
                    ->listWithLineBreaks()
                    ->toggleable()
                    ->visibleFrom('sm'),
                IconColumn::make('email_verified_at')
                    ->label(__('filament-resources.user.column_email_verified'))
                    ->default(false)
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('sm'),
                IconColumn::make('two_factor_authentication')
                    ->label(__('filament-resources.user.column_two_factor_authentication'))
                    ->state(fn (User $record): bool => $record->hasTwoFactorAuthentication())
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('sm'),
                IconColumn::make('can_login')
                    ->label(__('filament-resources.user.column_access_enabled'))
                    ->icon(Heroicon::OutlinedKey)
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->tooltip(fn (bool $state): string => $state
                        ? __('filament-resources.user.access_enabled_tooltip')
                        : __('filament-resources.user.access_disabled_tooltip'))
                    ->toggleable()
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('created_at')
                    ->label(__('filament-resources.user.column_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('sm'),
                TextColumn::make('updated_at')
                    ->label(__('filament-resources.user.column_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('sm'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['departments', 'roles']))
            ->filters([
                SelectFilter::make('departments')
                    ->label(__('filament-resources.user.filter_department'))
                    ->relationship('departments', 'key', fn (Builder $query): Builder => $query->ordered())
                    ->getOptionLabelFromRecordUsing(fn (Department $record): string => $record->getLabel())
                    ->multiple(),
                TernaryFilter::make('can_login')
                    ->label(__('filament-resources.user.filter_access'))
                    ->placeholder(fn (): string => __('filament-resources.user.filter_access_all', [
                        'count' => static::countUsers(),
                    ]))
                    ->trueLabel(fn (): string => __('filament-resources.user.filter_access_with_access', [
                        'count' => static::countUsers(fn (Builder $query): Builder => $query->where('can_login', true)),
                    ]))
                    ->falseLabel(fn (): string => __('filament-resources.user.filter_access_virtual', [
                        'count' => static::countUsers(fn (Builder $query): Builder => $query->where('can_login', false)),
                    ]))
                    ->indicateUsing(fn (array $state): array => match (blank($state['value'] ?? null) ? null : (bool) $state['value']) {
                        true => [Indicator::make(__('filament-resources.user.filter_access_indicator_with_access'))],
                        false => [Indicator::make(__('filament-resources.user.filter_access_indicator_virtual'))],
                        null => [],
                    }),
                TernaryFilter::make('email_verified_at')
                    ->label(__('filament-resources.user.filter_email_verified'))
                    ->placeholder(fn (): string => __('filament-resources.user.filter_all', [
                        'count' => static::countUsers(),
                    ]))
                    ->trueLabel(fn (): string => __('filament-resources.user.filter_email_verified_yes', [
                        'count' => static::countUsers(fn (Builder $query): Builder => $query->whereNotNull('email_verified_at')),
                    ]))
                    ->falseLabel(fn (): string => __('filament-resources.user.filter_email_verified_no', [
                        'count' => static::countUsers(fn (Builder $query): Builder => $query->whereNull('email_verified_at')),
                    ]))
                    ->nullable()
                    ->indicateUsing(fn (array $state): array => match (blank($state['value'] ?? null) ? null : (bool) $state['value']) {
                        true => [Indicator::make(__('filament-resources.user.filter_email_verified_indicator_yes'))],
                        false => [Indicator::make(__('filament-resources.user.filter_email_verified_indicator_no'))],
                        null => [],
                    }),
                TernaryFilter::make('two_factor_authentication')
                    ->label(__('filament-resources.user.filter_two_factor_authentication'))
                    ->placeholder(fn (): string => __('filament-resources.user.filter_all', [
                        'count' => static::countUsers(),
                    ]))
                    ->trueLabel(fn (): string => __('filament-resources.user.filter_two_factor_authentication_yes', [
                        'count' => static::countUsers(fn (Builder $query): Builder => $query->whereNotNull('app_authentication_secret')),
                    ]))
                    ->falseLabel(fn (): string => __('filament-resources.user.filter_two_factor_authentication_no', [
                        'count' => static::countUsers(fn (Builder $query): Builder => $query->whereNull('app_authentication_secret')),
                    ]))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('app_authentication_secret'),
                        false: fn (Builder $query): Builder => $query->whereNull('app_authentication_secret'),
                    )
                    ->indicateUsing(fn (array $state): array => match (blank($state['value'] ?? null) ? null : (bool) $state['value']) {
                        true => [Indicator::make(__('filament-resources.user.filter_two_factor_authentication_indicator_yes'))],
                        false => [Indicator::make(__('filament-resources.user.filter_two_factor_authentication_indicator_no'))],
                        null => [],
                    }),
            ])
            ->recordActions([
                ResendInvitationAction::make(),
                Impersonate::make()
                    ->requiresConfirmation()
                    ->redirectTo('/'),
                EditAction::make()
                    ->disabled(function (User $record) {
                        $currentUser = Auth::user();

                        return $record->isRootUserProtected() &&
                               ! ($currentUser instanceof User && $currentUser->isRootUser());
                    })
                    ->tooltip(function (User $record) {
                        $currentUser = Auth::user();

                        return $record->isRootUserProtected() &&
                               ! ($currentUser instanceof User && $currentUser->isRootUser())
                            ? __('profile.root_user_protection.edit_action_disabled')
                            : null;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AddToDepartmentBulkAction::make(),
                    DeleteUsersBulkAction::make(),
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
            ManageUserPublicationRequests::class,
        ]);
    }

    /**
     * Count the users listed in the table, optionally only those matching the given constraint.
     *
     * @param  (Closure(Builder): Builder)|null  $constraint
     */
    public static function countUsers(?Closure $constraint = null): int
    {
        return static::getEloquentQuery()
            ->when($constraint, $constraint)
            ->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
            'assignments' => ManageUserAssignments::route('/{record}/assignments'),
            'publication-requests' => ManageUserPublicationRequests::route('/{record}/publication-requests'),
        ];
    }
}
