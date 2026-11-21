<?php

namespace App\Filament\Resources\TerritoryAssignments\Actions;

use App\Models\TerritoryAssignment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Ask the territory department to close an assignment still in progress, with one click.
 *
 * Available to the assignee, and on the shared page of the assignment (no account needed) when
 * `GUEST_ALLOW_RETURNING_ASSIGNMENT` is enabled.
 */
class RequestReturnAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'requestReturn';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-resources.territory_assignment.action_request_return'))
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color('primary')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->modalHeading(__('filament-resources.territory_assignment.request_return_modal_heading'))
            ->modalDescription(__('filament-resources.territory_assignment.request_return_modal_description'))
            ->modalSubmitActionLabel(__('filament-resources.territory_assignment.action_request_return'))
            ->authorize(fn (TerritoryAssignment $record): bool => static::canRequestReturn($record))
            ->action(function (TerritoryAssignment $record): void {
                $requester = Auth::user();

                if (! $record->requestReturn($requester instanceof User ? $requester : null)) {
                    Notification::make()
                        ->warning()
                        ->title(__('filament-resources.territory_assignment.request_return_failure'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('filament-resources.territory_assignment.request_return_success_title'))
                    ->body(__('filament-resources.territory_assignment.request_return_success_body'))
                    ->send();
            });
    }

    /**
     * Whether the current visitor can ask to return the assignment: its assignee, or anyone with its shared link
     * when enabled.
     */
    public static function canRequestReturn(TerritoryAssignment $assignment): bool
    {
        if (Filament::getCurrentPanel()?->getId() === 'guest') {
            return config('app.guest_allow_returning_assignment')
                && $assignment->shared !== null
                && $assignment->canRequestReturn();
        }

        // Also checked here since the super administrators pass every gate: the others can close the assignment instead
        return $assignment->assignee_id === Auth::id()
            && Gate::allows('requestReturn', $assignment);
    }
}
