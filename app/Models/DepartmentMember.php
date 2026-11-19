<?php

namespace App\Models;

use App\Enums\DepartmentRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The membership of a user in a department.
 */
class DepartmentMember extends Pivot
{
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
}
