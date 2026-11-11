<?php

namespace App\Filament\Resources\TerritoryAssignments\Actions;

use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CreateAssignmentAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon(Heroicon::OutlinedPlus)
            ->label(__('filament-resources.territory_assignment.action_assign_territory'))
            ->color('primary')
            ->authorize(fn (): bool => Gate::allows('create', TerritoryAssignment::class))
            ->visible(function (Territory $record): bool {
                $assignment = $record->currentAssignment;

                return $assignment === null || $assignment->ended_at !== null;
            })
            ->schema([
                Select::make('assignee_id')
                    ->label(__('filament-resources.territory_assignment.action_assigned_to'))
                    ->options(fn (): array => User::getAssigneeOptions())
                    ->searchable()
                    ->required(),
                Select::make('campaign_id')
                    ->label(__('filament-resources.territory_assignment.field_campaign'))
                    ->options(fn (): array => Campaign::query()->notTerminated()->pluck('name', 'id')->all())
                    ->placeholder(__('filament-resources.territory_assignment.placeholder_campaign')),
            ])
            ->action(function (Territory $record, array $data): void {
                // Check if campaign is terminated
                if (filled($data['campaign_id'] ?? null) && Campaign::find($data['campaign_id'])?->isTerminated()) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament-resources.database_notifications.assignment_terminated_campaign_title'))
                        ->body(__('filament-resources.database_notifications.assignment_terminated_campaign_body'))
                        ->send();

                    // Keep the modal open, so that a different campaign can be selected
                    $this->halt();
                }

                try {
                    // The transaction makes the "already assigned" check (see TerritoryAssignmentObserver) atomic
                    DB::transaction(fn () => $record->assignments()->create([
                        'assignee_id' => $data['assignee_id'],
                        'territory_id' => $record->id,
                        'campaign_id' => $data['campaign_id'] ?? null,
                        'created_by' => Auth::id(),
                    ]));
                } catch (Exception $e) {
                    Log::error('Errore durante l\'assegnazione del territorio: '.$e->getMessage());
                    $this->failure();

                    return;
                }

                $this->success();
            })
            // ->successNotificationTitle('Territorio assegnato')
            ->successNotification(function (Notification $notification): Notification {
                return $notification
                    ->success()
                    ->title(__('filament-resources.database_notifications.assignment_success_title'))
                    ->body(__('filament-resources.database_notifications.assignment_success_body'));
            })
            ->failureNotification(function (Notification $notification): Notification {
                return $notification
                    ->danger()
                    ->title(__('filament-resources.database_notifications.assignment_error_title'))
                    ->body(__('filament-resources.database_notifications.assignment_error_body'));
            })
            ->modalHeading(__('filament-resources.database_notifications.create_assignment_modal'))
            ->modalSubmitAction(fn (Action $action) => $action->label(__('filament-resources.territory_assignment.action_create')));
    }
}
