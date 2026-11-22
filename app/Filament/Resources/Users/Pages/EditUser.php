<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\ResendInvitationAction;
use App\Filament\Resources\Users\Pages\Concerns\SendsUserInvitation;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use SendsUserInvitation;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareInvitation($data, $this->record);
    }

    protected function afterSave(): void
    {
        $this->sendInvitationIfNeeded();
    }

    protected function getHeaderActions(): array
    {
        return [
            ResendInvitationAction::make(),
            DeleteAction::make()
                ->visible(function () {
                    $record = $this->record;

                    return ! $record->isRootUser();
                }),
        ];
    }
}
