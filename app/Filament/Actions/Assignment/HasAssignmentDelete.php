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
            ->label('Termina assegnazione')
            ->modalHeading('Termina assegnazione')
            ->modalSubmitAction(fn (StaticAction $action) => $action->label('Termina'))
            ->color('primary')
            ->visible(function (Models\Territory $record): bool {
                $assignment = $record->currentAssignment();
                return $assignment !== null && $assignment->date_end === null;
            })
            ->mountUsing(function (Forms\ComponentContainer $form, Models\Territory $record) {
                $last_assignment = $record
                    ->currentAssignment()
                    ->with('assignee', 'assignmentType')
                ->first();

                Log::info('Last assignment: ' . $last_assignment);

                return $form->fill([
                    'assignee_id' => $last_assignment->assignee->id,
                    'assignment_type' => $last_assignment->assignmentType->id,
                ]);
            })
            ->form([
                Forms\Components\Select::make('assignee_id')
                    ->label('Assegnato a')
                    ->options(Models\User::get(['id', 'first_name', 'last_name'])->pluck('full_name', 'id')->toArray())
                    ->searchable()
                    ->disabled()
                    ->required(),
                Forms\Components\Select::make('assignment_type')
                    ->label('Tipo di assegnazione')
                    ->options(Models\TerritoryAssignmentType::query()->pluck('name', 'id'))
                    ->disabled()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (Models\Territory $record, array $data): void {
                try{
                    $last_assignment = $record->currentAssignment();

                    // Skip if the last assignment is not null
                    if ($last_assignment === null || $last_assignment->date_end !== null) {
                        Log::warning("Skipping record {$record->id} because it does not has an active assignment");
                        return;
                    }
                    
                    $last_assignment->date_end = now();
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
                    ->title('Territorio rientrato')
                    ->body('Il territorio è rientrato con successo.');
            })
            ->failureNotification(function (Notification $notification): Notification {
                return $notification
                    ->error()
                    ->title('Errore durante l\'assegnazione')
                    ->body('Si è verificato un errore durante il rientro del territorio.');
            });
    }
}