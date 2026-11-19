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
                    ->recordSelectSearchColumns(['first_name', 'last_name', 'email'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->orderBy('first_name')->orderBy('last_name'))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label(__('departments.members.field_users'))
                            ->hiddenLabel(false),
                        static::getRoleField(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('departments.members.action_change_role'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->modalHeading(fn (User $record): string => $record->getFullName())
                    ->authorize(fn (): bool => $this->canManageMembers()),
                DetachAction::make()
                    ->label(__('departments.members.action_remove'))
                    ->modalSubmitActionLabel(__('departments.members.action_remove'))
                    ->successNotificationTitle(__('departments.members.notification_removed'))
                    ->modalHeading(fn (User $record): string => __('departments.members.action_remove_heading', ['name' => $record->getFullName()]))
                    ->authorize(fn (): bool => $this->canManageMembers()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label(__('departments.members.action_remove'))
                        ->authorize(fn (): bool => $this->canManageMembers()),
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
