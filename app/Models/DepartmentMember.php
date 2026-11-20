<?php

namespace App\Models;

use App\Enums\DepartmentRole;
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['department_id', 'user_id', 'role']);
    }
}
