<?php
namespace App\Filament\Actions\Assignment;

use App\Models;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait HasAssignmentCreate
{
    protected function setUp(): void
    {
        parent::setUp();
 
        $this
            ->icon('heroicon-o-plus')
            ->label('Assegna territorio')
            ->color('primary')
            ->visible(function (Models\Territory $record): bool {
                $assignment = $record->currentAssignment();
                return $assignment === null || $assignment->date_end !== null;
            })
            ->form([
                Forms\Components\Select::make('assignee_id')
                    ->label('Assegnato a')
                    ->options(Models\User::query()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('assignment_type')
                    ->label('Tipo di assegnazione')
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
                    ->title('Territorio assegnato')
                    ->body('Il territorio è stato assegnato con successo.');
            })
            ->failureNotification(function (Notification $notification): Notification {
                return $notification
                    ->error()
                    ->title('Errore durante l\'assegnazione')
                    ->body('Si è verificato un errore durante l\'assegnazione del territorio.');
            })
            ->modalHeading('Crea nuova assegnazione')
            ->modalSubmitAction(fn (StaticAction $action) => $action->label('Crea'));
    }
}