<?php
namespace App\Filament\Resources\TerritoryAssignments\Actions;

use Filament\Actions\Action;
use App\Models\Territory;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\User;
use App\Models\Campaign;
use Exception;
use App\Models;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

class ReturnAssignmentAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->label(__('filament-resources.territory_assignment.action_end_assignment'))
            ->modalHeading(__('filament-resources.database_notifications.end_assignment_modal'))
            ->modalSubmitAction(fn(Action $action) => $action->label(__('filament-resources.territory_assignment.action_end')))
            ->color('primary')
            ->visible(function (Territory $record): bool {
                $assignment = $record->currentAssignment;
                return $assignment !== null && $assignment->ended_at === null;
            })
            ->mountUsing(function (Schema $schema, Territory $record) {
                $last_assignment = $record->currentAssignment;
                if (!$last_assignment) {
                    return $schema;
                }

                $last_assignment->load('assignee', 'campaign');

                Log::info('Last assignment: ' . $last_assignment);

                return $schema->fill([
                    'assignee_id' => $last_assignment->assignee->id,
                    'campaign_id' => $last_assignment->campaign?->id, // Campaign is optional
                ]);
            })
            ->schema([
                Select::make('assignee_id')
                    ->label(__('filament-resources.territory_assignment.action_assigned_to'))
                    ->options(User::get(['id', 'first_name', 'last_name'])->pluck('full_name', 'id')->toArray())
                    ->searchable()
                    ->disabled()
                    ->required(),
                Select::make('campaign_id')
                    ->label(__('filament-resources.territory_assignment.field_campaign'))
                    ->options(Campaign::query()->pluck('name', 'id'))
                    ->placeholder(__('filament-resources.territory_assignment.placeholder_campaign'))
                    ->nullable()
                    ->disabled()
                    ->native(false),
            ])
            ->requiresConfirmation()
            ->action(function (Territory $record, array $data): void {
                try {
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
                } catch (Exception $e) {
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
                    ->title(__('filament-resources.database_notifications.territory_returned_error_title'))
                    ->body(__('filament-resources.database_notifications.territory_returned_error_body'));
            });
    }
}
