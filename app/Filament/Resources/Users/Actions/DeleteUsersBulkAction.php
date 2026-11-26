<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use App\Observers\UserObserver;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Delete the users selected in the list. The root user and the users who have assignments
 * ({@see UserObserver::guardDeletion()}) are skipped, and the notification says why.
 */
class DeleteUsersBulkAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->action(function (): void {
                $this->process(static function (DeleteUsersBulkAction $action, Collection $records): void {
                    $isFirstException = true;

                    $records->each(static function (User $record) use ($action, &$isFirstException): void {
                        if ($record->isRootUser()) {
                            $action->reportBulkProcessingFailure(
                                'root_user',
                                __('profile.root_user_protection.account_deletion_not_allowed'),
                            );

                            return;
                        }

                        if ($record->isLastSuperAdmin()) {
                            $action->reportBulkProcessingFailure(
                                'last_super_admin',
                                fn (int $failureCount): string => trans_choice('filament-resources.user.delete_refused_last_super_admin_bulk', $failureCount),
                            );

                            return;
                        }

                        // As in `DeleteBulkAction`, an error (e.g. an assignment created in the meantime, which the
                        // database refuses) skips the user without stopping the deletion of the others
                        try {
                            $isDeleted = $record->delete();
                        } catch (Throwable $exception) {
                            $action->reportBulkProcessingFailure();

                            if ($isFirstException) {
                                report($exception);

                                $isFirstException = false;
                            }

                            return;
                        }

                        if (! $isDeleted) {
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
