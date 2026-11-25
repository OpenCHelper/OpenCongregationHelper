<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use App\Observers\UserObserver;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Delete a user from their page. The root user cannot be deleted, and neither can a user who has assignments
 * ({@see UserObserver::guardDeletion()}): the modal explains why, and offers to disable their access instead, making them a
 * virtual user whose assignments are kept.
 */
class DeleteUserAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->visible(fn (User $record): bool => ! $record->isRootUser())
            ->modalDescription(fn (User $record): string => $record->hasAssignments()
                ? static::getRefusalExplanation($record)
                : __('filament-actions::modal.confirmation'))
            ->modalSubmitAction(fn (User $record): ?bool => $record->hasAssignments() ? false : null)
            ->extraModalFooterActions(fn (): array => [static::makeDisableAccessAction()])
            ->failureNotificationTitle(__('filament-resources.user.delete_refused_title'))
            ->failureNotificationBody(fn (User $record): string => static::getRefusalExplanation($record));
    }

    /**
     * Why the user cannot be deleted, and what to do instead when they can still log in.
     */
    public static function getRefusalExplanation(User $user): string
    {
        $explanation = __('filament-resources.user.delete_refused_body');

        if ($user->can_login) {
            $explanation .= ' '.__('filament-resources.user.delete_refused_disable_access');
        }

        return $explanation;
    }

    /**
     * Disable the access of the user (`can_login`) in place of deleting them.
     */
    protected static function makeDisableAccessAction(): Action
    {
        return Action::make('disableAccess')
            ->label(__('filament-resources.user.action_disable_access'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->visible(fn (User $record): bool => $record->can_login && $record->hasAssignments() && Gate::allows('update', $record))
            ->action(function (User $record, Component $livewire): void {
                $record->update(['can_login' => false]);

                // The page shows the access of the user too
                if (method_exists($livewire, 'refreshFormData')) {
                    $livewire->refreshFormData(['can_login']);
                }

                Notification::make()
                    ->success()
                    ->title(__('filament-resources.user.access_disabled', ['name' => $record->name]))
                    ->send();
            })
            ->cancelParentActions();
    }
}
