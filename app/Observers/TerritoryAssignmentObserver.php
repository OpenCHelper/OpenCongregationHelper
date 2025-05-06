<?php

namespace App\Observers;

use App\Filament\Resources\TerritoryAssignmentResource;
use App\Models\TerritoryAssignment;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TerritoryAssignmentObserver
{
    /**
     * Handle the TerritoryAssignment "created" event.
     */
    public function created(TerritoryAssignment $territoryAssignment): void
    {
        Log::info('TerritoryAssignment created', [
            'territory_assignment' => $territoryAssignment,
        ]);
        
        Notification::make()
            ->title('Territorio assegnato')
            ->body('Ti è stato assegnato un nuovo territorio.')
            ->icon('heroicon-o-map')
            ->actions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(TerritoryAssignmentResource::getUrl('view', [
                        'record' => $territoryAssignment->id,
                    ]))
                    ->label('Vedi'),
            ])
            ->sendToDatabase($territoryAssignment->assignee);
    }

    /**
     * Handle the TerritoryAssignment "updated" event.
     */         
    public function updated(TerritoryAssignment $territoryAssignment): void
    {
        Log::info('TerritoryAssignment updated', [
            'territory_assignment' => $territoryAssignment,
        ]);
        
        if ($territoryAssignment->isDirty('ended_at') && $territoryAssignment->ended_at !== null) {
            Notification::make()
                ->title('Territorio riconsegnato')
                ->body('Il tuo territorio è stato riconsegnato.')
                ->icon('heroicon-o-inbox-arrow-down')
                ->sendToDatabase($territoryAssignment->assignee);
        }
    }

    /**
     * Handle the TerritoryAssignment "deleted" event.
     */
    public function deleted(TerritoryAssignment $territoryAssignment): void
    {
        //
    }

    /**
     * Handle the TerritoryAssignment "restored" event.
     */
    public function restored(TerritoryAssignment $territoryAssignment): void
    {
        //
    }

    /**
     * Handle the TerritoryAssignment "force deleted" event.
     */
    public function forceDeleted(TerritoryAssignment $territoryAssignment): void
    {
        //
    }
}
