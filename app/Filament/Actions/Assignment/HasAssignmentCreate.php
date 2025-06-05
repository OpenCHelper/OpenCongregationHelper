<?php
namespace App\Filament\Actions\Assignment;

use App\Models;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// From [here](https://filamentglow.com/trick/reuse-action-in-both-table-and-header-702ad05b)
trait HasAssignmentCreate
{
    protected function setUp(): void
    {
        parent::setUp();
 
        $this
            ->icon('heroicon-o-plus')
            ->label(__('filament-resources.territory_assignment.action_assign_territory'))
            ->color('primary')
            ->visible(function (Models\Territory $record): bool {
                $assignment = $record->currentAssignment();
                return $assignment === null || $assignment->ended_at !== null;
            })
            ->form([
                Forms\Components\Select::make('assignee_id')
                    ->label(__('filament-resources.territory_assignment.action_assigned_to'))
                    ->options(Models\User::get(['id', 'first_name', 'last_name'])->pluck('full_name', 'id')->toArray())
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('assignment_type')
                    ->label(__('filament-resources.territory_assignment.field_assignment_type'))
                    ->options(Models\TerritoryAssignmentType::query()->pluck('name', 'id'))
                    ->required(),
            ])
            ->action(function (Models\Territory $record, array $data): void {
                try{
                    $record->assignments()->create([
                        'assignee_id' => $data['assignee_id'],
                        'territory_id' => $record->id,
                        'territory_assignment_type_id' => $data['assignment_type'],
                        'created_by' => Auth::id(),
                    ]);
                    $record->save();
                } catch (\Exception $e) {
                    Log::error('Errore durante l\'assegnazione del territorio: ' . $e->getMessage());
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
                    ->title(__('filament-resources.database_notifications.assignment_success_title'))
                    ->body(__('filament-resources.database_notifications.assignment_success_body'));
            })
            ->failureNotification(function (Notification $notification): Notification {
                return $notification
                    ->error()
                    ->title(__('filament-resources.database_notifications.assignment_error_title'))
                    ->body(__('filament-resources.database_notifications.assignment_error_body'));
            })
            ->modalHeading(__('filament-resources.database_notifications.create_assignment_modal'))
            ->modalSubmitAction(fn (StaticAction $action) => $action->label(__('filament-resources.territory_assignment.action_create')));
    }
}