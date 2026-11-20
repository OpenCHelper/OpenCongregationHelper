<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Models\Department;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Department $department */
        $department = $this->getRecord();

        return $department->getLabel();
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Department $department */
        $department = $this->getRecord();

        return $department->key->getDescription();
    }
}
