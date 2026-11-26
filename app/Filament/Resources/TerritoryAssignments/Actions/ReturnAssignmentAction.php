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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Return a territory, closing its assignment in progress.
 *
 * Used on the territories (whose current assignment is closed) and on the assignments themselves (e.g. their list,
 * the assignment history of a territory, a user or a campaign, and the assignment page).
 */
class ReturnAssignmentAction extends Action
{
    /**
     * The assignment in progress the action closes: the record itself when it is an assignment, or the current
     * assignment of a territory. Null when there is none (e.g. a returned or deleted assignment), or when it starts in
     * the future: it cannot end before it starts.
     */
    public static function getAssignmentInProgress(Model $record): ?TerritoryAssignment
    {
        $assignment = $record instanceof Territory ? $record->currentAssignment : $record;

        if (
            ! $assignment instanceof TerritoryAssignment
            || $assignment->ended_at !== null
            || $assignment->trashed()
            || $assignment->started_at?->isFuture()
        ) {
            return null;
        }

        return $assignment;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->label(__('filament-resources.territory_assignment.action_end_assignment'))
            ->modalHeading(__('filament-resources.database_notifications.end_assignment_modal'))
            ->modalSubmitAction(fn (Action $action) => $action->label(__('filament-resources.territory_assignment.action_end')))
            ->color('primary')
            ->authorize(function (Model $record): bool {
                $assignment = static::getAssignmentInProgress($record);

                return $assignment !== null && Gate::allows('update', $assignment);
            })
            ->visible(fn (Model $record): bool => static::getAssignmentInProgress($record) !== null)
            ->mountUsing(function (Schema $schema, Model $record) {
                $last_assignment = static::getAssignmentInProgress($record);
                if (! $last_assignment) {
                    return $schema;
                }

                $last_assignment->load('assignee', 'campaign');

                return $schema->fill([
                    'assignee_id' => $last_assignment->assignee->id,
                    'campaign_id' => $last_assignment->campaign?->id, // Campaign is optional
                ]);
            })
            ->schema([
                Select::make('assignee_id')
                    ->label(__('filament-resources.territory_assignment.action_assigned_to'))
                    ->options(fn (): array => User::getAssigneeOptions())
                    ->searchable()
                    ->disabled()
                    ->required(),
                Select::make('campaign_id')
                    ->label(__('filament-resources.territory_assignment.field_campaign'))
                    ->options(fn (): array => Campaign::query()->pluck('name', 'id')->all())
                    ->placeholder(__('filament-resources.territory_assignment.placeholder_campaign'))
                    ->nullable()
                    ->disabled()
                    ->native(false),
            ])
            ->requiresConfirmation()
            ->action(function (Model $record): void {
                $last_assignment = static::getAssignmentInProgress($record);

                // Skip if there is no assignment in progress anymore
                if ($last_assignment === null) {
                    Log::warning("Skipping record {$record->getKey()} because it does not has an active assignment");

                    return;
                }

                try {
                    // Carica la relazione territory prima dell'update per evitare lazy loading errors
                    $last_assignment->load('territory');
                    $last_assignment->ended_at = now();
                    $last_assignment->save();
                } catch (Exception $e) {
                    Log::error('Errore durante la terminazione dell\'assegnazione del territorio: '.$e->getMessage());
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
                    ->danger()
                    ->title(__('filament-resources.database_notifications.territory_returned_error_title'))
                    ->body(__('filament-resources.database_notifications.territory_returned_error_body'));
            });
    }
}
