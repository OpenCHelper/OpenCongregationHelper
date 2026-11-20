<?php

namespace App\Filament\Resources\Departments\RelationManagers;

use App\Enums\DepartmentRole;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    /**
     * The role of the members, which must be the overseer when the department has none yet (`$overseerRequired`): its
     * first member is its overseer, so that the assistants are never left without one.
     */
    public static function getRoleField(bool $overseerRequired = false): ToggleButtons
    {
        return ToggleButtons::make('role')
            ->label(__('departments.members.field_role'))
            ->options(DepartmentRole::class)
            ->default($overseerRequired ? DepartmentRole::Overseer->value : DepartmentRole::Assistant->value)
            // Only the administrators appoint the overseers (checked again on save)
            ->disableOptionWhen(fn (string $value): bool => $value === DepartmentRole::Overseer->value
                ? ! static::canManageOverseers()
                : $overseerRequired)
            ->in(fn (): array => match (true) {
                $overseerRequired => [DepartmentRole::Overseer->value],
                static::canManageOverseers() => array_map(fn (DepartmentRole $role): string => $role->value, DepartmentRole::cases()),
                default => [DepartmentRole::Assistant->value],
            })
            ->inline()
            ->required()
            ->helperText($overseerRequired
                ? __('departments.members.field_role_helper_first_overseer')
                : __('departments.members.field_role_helper'));
    }

    /**
     * Whether the member is the only overseer of the department, who cannot be demoted or removed until another
     * overseer is appointed.
     */
    protected function isOnlyOverseer(User $member): bool
    {
        /** @var Department $department */
        $department = $this->getOwnerRecord();

        return $department->isOnlyOverseer($member);
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
                            ->hiddenLabel(false),
                        static::getRoleField(overseerRequired: $department->overseers()->doesntExist()),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('departments.members.action_change_role'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->modalHeading(fn (User $record): string => $record->getFullName())
                    ->authorize(fn (User $record): bool => $this->canManageMember($record))
                    // Demoting is the only change of role, refused for the only overseer (checked again on save)
                    ->disabled(fn (User $record): bool => $this->isOnlyOverseer($record))
                    ->tooltip(fn (User $record): ?string => $this->isOnlyOverseer($record) ? __('departments.members.last_overseer') : null),
                DetachAction::make()
                    ->label(__('departments.members.action_remove'))
                    ->modalSubmitActionLabel(__('departments.members.action_remove'))
                    ->successNotificationTitle(__('departments.members.notification_removed'))
                    ->modalHeading(fn (User $record): string => __('departments.members.action_remove_heading', ['name' => $record->getFullName()]))
                    ->authorize(fn (User $record): bool => $this->canManageMember($record))
                    ->disabled(fn (User $record): bool => $this->isOnlyOverseer($record))
                    ->tooltip(fn (User $record): ?string => $this->isOnlyOverseer($record) ? __('departments.members.last_overseer') : null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label(__('departments.members.action_remove'))
                        ->authorize(fn (): bool => $this->canManageMembers())
                        // The overseers selected are kept, unless an administrator removes them
                        ->authorizeIndividualRecords(fn (User $record): bool => $this->canManageMember($record))
                        // The whole selection is checked: removing two overseers can leave none, even if each one alone could go
                        ->before(function (DetachBulkAction $action, Collection $records) use ($department): void {
                            $removed = $records->filter(fn (User $record): bool => $this->canManageMember($record));

                            if (! $department->wouldLoseEveryOverseer($removed->map(fn (User $record): int|string => $record->getKey()))) {
                                return;
                            }

                            Notification::make()
                                ->danger()
                                ->title(__('departments.members.last_overseer'))
                                ->send();

                            $action->cancel();
                        }),
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
