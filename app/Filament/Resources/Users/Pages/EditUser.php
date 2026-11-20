<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(function () {
                    $record = $this->record;

                    return ! $record->isRootUser();
                })
                // The only overseer of a department is kept until another one is appointed
                ->disabled(fn (User $record): bool => $record->isLastDepartmentOverseer())
                ->tooltip(fn (User $record): ?string => $record->isLastDepartmentOverseer()
                    ? __('departments.users.delete_last_overseer')
                    : null),
        ];
    }
}
