<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use App\Observers\UserObserver;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;

/**
 * Delete the users selected in the list. The root user and the users who have assignments
 * ({@see UserObserver::deleting()}) are skipped, and the notification says why.
 */
class DeleteUsersBulkAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->action(function (): void {
                $this->process(static function (DeleteUsersBulkAction $action, Collection $records): void {
                    $records->each(static function (User $record) use ($action): void {
                        if ($record->isRootUser()) {
                            $action->reportBulkProcessingFailure(
                                'root_user',
                                __('profile.root_user_protection.account_deletion_not_allowed'),
                            );

                            return;
                        }

                        if (! $record->delete()) {
                            $action->reportBulkProcessingFailure(
                                'has_assignments',
                                fn (int $failureCount): string => trans_choice('filament-resources.user.delete_refused_bulk', $failureCount),
                            );
                        }
                    });
                });
            });
    }
}
