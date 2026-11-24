<?php

namespace App\Filament\Resources\Users\Actions;

use App\Filament\Resources\Departments\RelationManagers\MembersRelationManager;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Add the users selected in the list to a department, all with the same role, instead of adding them one by one
 * from the page of the department.
 *
 * The departments offered are the ones the current user can manage the members of (as on the page of the department),
 * and only the administrators appoint the overseers. The users already serving in the department keep their role.
 */
class AddToDepartmentBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'addToDepartment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('departments.bulk_add.action'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->modalHeading(__('departments.bulk_add.heading'))
            ->modalSubmitActionLabel(__('departments.members.action_add_submit'))
            ->authorize(fn (): bool => static::getManageableDepartments()->isNotEmpty())
            ->schema([
                Select::make('department')
                    ->label(__('departments.bulk_add.field_department'))
                    ->options(fn (): array => static::getManageableDepartments()
                        ->mapWithKeys(fn (Department $department): array => [$department->getKey() => $department->getLabel()])
                        ->all())
                    // Checked again on save: the options are only what is shown
                    ->in(fn (): array => static::getManageableDepartments()->modelKeys())
                    ->required(),
                MembersRelationManager::getRoleField(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $department = static::getManageableDepartments()->find($data['department']);

                if ($department === null) {
                    return;
                }

                $existingMemberIds = $department->members()->whereKey($records->modelKeys())->pluck('users.id')->all();
                $newMembers = $records->reject(fn (User $user): bool => in_array($user->getKey(), $existingMemberIds));

                // One by one through the pivot model, so that each membership is logged
                $department->members()->attach($newMembers->modelKeys(), ['role' => $data['role']]);

                Notification::make()
                    ->success()
                    ->title(trans_choice('departments.bulk_add.notification_added', $newMembers->count(), [
                        'count' => $newMembers->count(),
                        'department' => $department->getLabel(),
                    ]))
                    ->body(count($existingMemberIds) > 0
                        ? trans_choice('departments.bulk_add.notification_skipped', count($existingMemberIds), ['count' => count($existingMemberIds)])
                        : null)
                    ->send();
            });
    }

    /**
     * Get the departments whose members the current user can manage, in their usual order.
     *
     * @return Collection<int, Department>
     */
    public static function getManageableDepartments(): Collection
    {
        return Department::query()
            ->ordered()
            ->get()
            ->filter(fn (Department $department): bool => Gate::allows('update', $department))
            ->values();
    }
}
