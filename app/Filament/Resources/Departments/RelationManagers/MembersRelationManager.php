<?php

namespace App\Filament\Resources\Departments\RelationManagers;

use App\Enums\DepartmentRole;
use App\Filament\Tables\Columns\RecordCardColumn;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The members of a department, managed by the administrators and by the overseers of the department.
 */
class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('departments.members.title');
    }

    public static function getModelLabel(): string
    {
        return __('departments.members.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('departments.members.plural_model_label');
    }

    /**
     * The members are shown to whoever can see the department, even without the permission to see the users.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    /**
     * The members can be changed from the page of the department (a view page, read-only by default).
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function canManageMembers(): bool
    {
        return Gate::allows('update', $this->getOwnerRecord());
    }

    /**
     * Only the administrators (who can update every department) appoint the overseers, or change and remove them: an
     * overseer manages the assistants of their department.
     */
    protected static function canManageOverseers(): bool
    {
        return Gate::allows('Update:Department');
    }

    /**
     * Whether the member can be edited or removed by the current user: an overseer only by an administrator.
     */
    protected function canManageMember(User $member): bool
    {
        if (! $this->canManageMembers()) {
            return false;
        }

        if (static::canManageOverseers()) {
            return true;
        }

        // The role of the membership, also when the user was loaded without it (e.g. the records selected in bulk)
        $role = $member->pivot?->getAttribute('role')
            ?? $this->getOwnerRecord()->members()->whereKey($member->getKey())->first()?->pivot?->getAttribute('role');

        return $role !== null && static::toRole($role) !== DepartmentRole::Overseer;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::getRoleField(),
            ]);
    }

    public static function getRoleField(): ToggleButtons
    {
        return ToggleButtons::make('role')
            ->label(__('departments.members.field_role'))
            ->options(DepartmentRole::class)
            ->default(DepartmentRole::Assistant->value)
            // Only the administrators appoint the overseers (checked again on save)
            ->disableOptionWhen(fn (string $value): bool => $value === DepartmentRole::Overseer->value && ! static::canManageOverseers())
            ->in(fn (): array => static::canManageOverseers()
                ? array_map(fn (DepartmentRole $role): string => $role->value, DepartmentRole::cases())
                : [DepartmentRole::Assistant->value])
            ->inline()
            ->required()
            ->helperText(__('departments.members.field_role_helper'));
    }

    public function table(Table $table): Table
    {
        /** @var Department $department */
        $department = $this->getOwnerRecord();

        return $table
            ->recordTitle(fn (User $record): string => $record->getFullName())
            // Overseers first ("overseer" comes after "assistant" in alphabetical order), then by name
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderBy('department_user.role', 'desc')
                ->orderBy('first_name')
                ->orderBy('last_name'))
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('departments.members.column_name'))
                    ->icon(Heroicon::OutlinedUser)
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('role')
                    ->label(__('departments.members.column_role'))
                    ->badge()
                    ->formatStateUsing(fn (DepartmentRole|string $state): string => static::toRole($state)->getLabel())
                    ->color(fn (DepartmentRole|string $state): string => static::toRole($state)->getColor())
                    ->icon(fn (DepartmentRole|string $state) => static::toRole($state)->getIcon()),
                TextColumn::make('email')
                    ->label(__('departments.members.column_email'))
                    ->icon(Heroicon::Envelope)
                    ->copyable()
                    ->visibleFrom('md'),
            ])
            ->tap(fn (Table $table) => RecordCardColumn::make()->heading('full_name')->aside('role')->fullWidth('email')->addTo($table))
            ->headerActions([
                AttachAction::make()
                    ->label(__('departments.members.action_add'))
                    ->modalHeading(__('departments.members.action_add_heading', ['department' => $department->getLabel()]))
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->modalSubmitActionLabel(__('departments.members.action_add_submit'))
                    ->attachAnother(false)
                    ->successNotificationTitle(__('departments.members.notification_added'))
                    ->authorize(fn (): bool => $this->canManageMembers())
                    ->multiple()
                    ->preloadRecordSelect()
                    // By name only: the overseers may not see the users, so their email addresses are not searchable
                    ->recordSelectSearchColumns(['first_name', 'last_name'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->orderBy('first_name')->orderBy('last_name'))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label(__('departments.members.field_users'))
                            ->hiddenLabel(false)
                            ->helperText(__('departments.members.field_users_helper')),
                        static::getRoleField(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('departments.members.action_change_role'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->modalHeading(fn (User $record): string => $record->getFullName())
                    ->authorize(fn (User $record): bool => $this->canManageMember($record)),
                DetachAction::make()
                    ->label(__('departments.members.action_remove'))
                    ->modalSubmitActionLabel(__('departments.members.action_remove'))
                    ->successNotificationTitle(__('departments.members.notification_removed'))
                    ->modalHeading(fn (User $record): string => __('departments.members.action_remove_heading', ['name' => $record->getFullName()]))
                    ->authorize(fn (User $record): bool => $this->canManageMember($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label(__('departments.members.action_remove'))
                        ->authorize(fn (): bool => $this->canManageMembers())
                        // The overseers selected are kept, unless an administrator removes them
                        ->authorizeIndividualRecords(fn (User $record): bool => $this->canManageMember($record)),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading(__('departments.members.empty_heading'))
            ->emptyStateDescription(__('departments.members.empty_description'));
    }

    private static function toRole(DepartmentRole|string $state): DepartmentRole
    {
        return $state instanceof DepartmentRole ? $state : (DepartmentRole::tryFrom($state) ?? DepartmentRole::Assistant);
    }
}
