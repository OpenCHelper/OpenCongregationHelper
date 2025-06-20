<?php
namespace App\Filament\Actions\Assignment;

use App\Models;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

trait HasAssignmentDelete
{
    protected function setUp(): void
    {
        parent::setUp();
 
        $this
            ->icon('heroicon-o-archive-box-arrow-down')
            ->label(__('filament-resources.territory_assignment.action_end_assignment'))
            ->modalHeading(__('filament-resources.database_notifications.end_assignment_modal'))
            ->modalSubmitAction(fn (StaticAction $action) => $action->label(__('filament-resources.territory_assignment.action_end')))
            ->color('primary')
            ->visible(function (Models\Territory $record): bool {
                $assignment = $record->currentAssignment;
                return $assignment !== null && $assignment->ended_at === null;
            })
            ->mountUsing(function (Forms\ComponentContainer $form, Models\Territory $record) {
                $last_assignment = $record->currentAssignmentRelation;
                if ($last_assignment) {
                    $last_assignment->load('assignee', 'campaign');
                }

                Log::info('Last assignment: ' . $last_assignment);

                return $form->fill([
                    'assignee_id' => $last_assignment->assignee->id,
                    'campaign_id' => $last_assignment->campaign->id,
                ]);
            })
            ->form([
                Forms\Components\Select::make('assignee_id')
                    ->label(__('filament-resources.territory_assignment.action_assigned_to'))
                    ->options(Models\User::get(['id', 'first_name', 'last_name'])->pluck('full_name', 'id')->toArray())
                    ->searchable()
                    ->disabled()
                    ->required(),
                Forms\Components\Select::make('campaign_id')
                    ->label(__('filament-resources.territory_assignment.field_campaign'))
                    ->options(Models\Campaign::query()->pluck('name', 'id'))
                    ->disabled(),
            ])
            ->requiresConfirmation()
            ->action(function (Models\Territory $record, array $data): void {
                try{
                    $last_assignment = $record->currentAssignment;

                    // Skip if the last assignment is not null
                    if ($last_assignment === null || $last_assignment->ended_at !== null) {
                        Log::warning("Skipping record {$record->id} because it does not has an active assignment");
                        return;
                    }
                    
                    // Carica la relazione territory prima dell'update per evitare lazy loading errors
                    $last_assignment->load('territory');
                    $last_assignment->ended_at = now();
                    $last_assignment->push();
                } catch (\Exception $e) {
                    Log::error('Errore durante la terminazione dell\'assegnazione del territorio: ' . $e->getMessage());
                    $this->halt();
                    $this->failure();
                    return;
                }

                $this->success();
            })
            // ->successNotificationTitle('Territorio assegnato')
            ->successNotification(function (Notification $notification): Notification {
                return $notification
                    ->success()
                    ->title(__('filament-resources.database_notifications.territory_returned_success_title'))
                    ->body(__('filament-resources.database_notifications.territory_returned_success_body'));
            })
            ->failureNotification(function (Notification $notification): Notification {
                return $notification
                    ->error()
                    ->title(__('filament-resources.database_notifications.assignment_error_title'))
                    ->body(__('filament-resources.database_notifications.territory_return_error_body'));
            });
    }
}