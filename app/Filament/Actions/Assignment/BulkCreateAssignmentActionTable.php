<?php
namespace App\Filament\Actions\Assignment;

use Filament\Tables\Actions\Action;
use App\Models;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BulkCreateAssignmentActionTable extends Action
{
    protected function setUp(): void
    {
        parent::setUp();
 
        $this
            ->icon('heroicon-o-plus')
            ->label(__('filament-resources.territory_assignment.action_assign_territory'))
            ->color('primary')
            ->form([
                Forms\Components\Select::make('assignee_id')
                    ->label(__('filament-resources.territory_assignment.action_assigned_to'))
                    ->options(Models\User::get(['id', 'first_name', 'last_name'])->pluck('full_name', 'id')->toArray())
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('campaign_id')
                    ->label(__('filament-resources.territory_assignment.field_campaign'))
                    ->options(Models\Campaign::query()
                        ->where(function ($query) {
                            $query->whereNull('valid_until')
                                  ->orWhere('valid_until', '>', now());
                        })
                        ->pluck('name', 'id'))
                    ->placeholder(__('filament-resources.territory_assignment.placeholder_campaign')),
            ])
            ->action(function (Collection $records, array $data): void {
                try{
                    // Check if campaign is terminated
                    if ($data['campaign_id'] && Models\Campaign::find($data['campaign_id'])?->isTerminated()) {
                        $this->halt();
                        $this->failure();
                        Notification::make()
                            ->error()
                            ->title(__('filament-resources.database_notifications.assignment_terminated_campaign_title'))
                            ->body(__('filament-resources.database_notifications.assignment_terminated_campaign_body'))
                            ->send();
                        return;
                    }

                    foreach ($records as $record) {
                        // Skip if the last assignment is not null
                        if ($record->currentAssignment !== null && $record->currentAssignment->ended_at === null) {
                            Log::warning("Skipping record {$record->id} because it has an active assignment");
                            continue;
                        }

                        $record->assignments()->create([
                            'assignee_id' => $data['assignee_id'],
                            'territory_id' => $record->id,
                            'campaign_id' => $data['campaign_id'] ?? null,
                            'created_by' => Auth::id(),
                        ]);
                        $record->save();
                    }
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
                    ->title(__('filament-resources.database_notifications.territories_assigned_success_title'))
                    ->body(__('filament-resources.database_notifications.territories_assigned_success_body'));
            })
            ->failureNotification(function (Notification $notification): Notification {
                return $notification
                    ->error()
                    ->title(__('filament-resources.database_notifications.assignment_error_title'))
                    ->body(__('filament-resources.database_notifications.assignment_error_body'));
            })
            ->modalHeading(__('filament-resources.database_notifications.create_bulk_assignment_modal'))
            ->modalSubmitAction(fn (StaticAction $action) => $action->label(__('filament-resources.territory_assignment.action_create')));
    }
}