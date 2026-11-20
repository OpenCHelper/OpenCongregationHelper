<?php

namespace App\Models;

use App\Enums\DepartmentRole;
use DomainException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The membership of a user in a department.
 *
 * Logged like a role: a member manages the sections of the department, so the activity log keeps who added, changed
 * or removed them (the relation uses this class, so attaching, updating and detaching fire its events).
 */
class DepartmentMember extends Pivot
{
    use LogsActivity;

    protected $table = 'department_user';

    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => DepartmentRole::class,
        ];
    }

    /**
     * A department that has an overseer must always keep one: demoting or removing the last overseer is refused,
     * whatever the page (the members' page disables these actions, explaining why).
     */
    protected static function booted(): void
    {
        static::updating(function (self $member): void {
            if ($member->getOriginal('role') === DepartmentRole::Overseer && $member->role !== DepartmentRole::Overseer) {
                $member->ensureDepartmentKeepsAnOverseer();
            }
        });

        // Detaching builds the pivot from the keys only, so the role is read from the database
        static::deleting(fn (self $member) => $member->ensureDepartmentKeepsAnOverseer());
    }

    /**
     * @throws DomainException when the user is the only overseer of the department
     */
    protected function ensureDepartmentKeepsAnOverseer(): void
    {
        $department = Department::query()->find($this->getAttribute('department_id'));
        $user = User::query()->find($this->getAttribute('user_id'));

        if ($department !== null && $user !== null && $department->isOnlyOverseer($user)) {
            throw new DomainException(__('departments.members.last_overseer'));
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['department_id', 'user_id', 'role']);
    }
}
