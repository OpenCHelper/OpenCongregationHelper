<?php

namespace App\Observers;

use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Notifications\TerritoryAssignedNotification;
use App\Notifications\TerritoryReturnedNotification;
use Illuminate\Validation\ValidationException;

class TerritoryAssignmentObserver
{
    /**
     * Handle the TerritoryAssignment "saving" event.
     */
    public function saving(TerritoryAssignment $territoryAssignment): void
    {
        if ($territoryAssignment->isDirty(['started_at', 'ended_at'])) {
            $this->ensureAssignmentDoesNotEndBeforeItStarts($territoryAssignment);
        }
    }

    /**
     * Handle the TerritoryAssignment "creating" event.
     */
    public function creating(TerritoryAssignment $territoryAssignment): void
    {
        $this->ensureCampaignIsNotTerminated($territoryAssignment);
        $this->ensureTerritoryIsNotAlreadyAssigned($territoryAssignment);
    }

    /**
     * Handle the TerritoryAssignment "updating" event.
     */
    public function updating(TerritoryAssignment $territoryAssignment): void
    {
        // Only a new campaign is checked: an assignment can still be edited (e.g. returned) after its campaign has ended
        if ($territoryAssignment->isDirty('campaign_id')) {
            $this->ensureCampaignIsNotTerminated($territoryAssignment);
        }

        if ($territoryAssignment->isDirty(['ended_at', 'territory_id'])) {
            $this->ensureTerritoryIsNotAlreadyAssigned($territoryAssignment);
        }
    }

    /**
     * Handle the TerritoryAssignment "restoring" event.
     */
    public function restoring(TerritoryAssignment $territoryAssignment): void
    {
        $this->ensureTerritoryIsNotAlreadyAssigned($territoryAssignment);
    }

    /**
     * An assignment cannot be returned before it is assigned.
     *
     * A new assignment saved without a start date is skipped: the database sets its start date when inserting it.
     *
     * @throws ValidationException
     */
    private function ensureAssignmentDoesNotEndBeforeItStarts(TerritoryAssignment $territoryAssignment): void
    {
        if ($territoryAssignment->started_at === null || $territoryAssignment->ended_at === null) {
            return;
        }

        if ($territoryAssignment->ended_at->lt($territoryAssignment->started_at)) {
            throw ValidationException::withMessages([
                'ended_at' => __('validation.territory_assignment.ended_before_started'),
            ]);
        }
    }

    /**
     * An assignment cannot be linked to a terminated campaign.
     *
     * @throws ValidationException
     */
    private function ensureCampaignIsNotTerminated(TerritoryAssignment $territoryAssignment): void
    {
        if ($territoryAssignment->campaign_id === null) {
            return;
        }

        if (Campaign::find($territoryAssignment->campaign_id)?->isTerminated()) {
            throw ValidationException::withMessages([
                'campaign_id' => __('validation.campaign.not_terminated'),
            ]);
        }
    }

    /**
     * A territory can have only one assignment in progress (not returned) at a time.
     *
     * @throws ValidationException
     */
    private function ensureTerritoryIsNotAlreadyAssigned(TerritoryAssignment $territoryAssignment): void
    {
        if ($territoryAssignment->ended_at !== null) {
            return;
        }

        // Lock the territory row, so that concurrent assignments of the same
        // territory (inside database transactions) are checked one at a time.
        Territory::query()
            ->withoutGlobalScopes()
            ->whereKey($territoryAssignment->territory_id)
            ->lockForUpdate()
            ->first();

        $ignoredAssignmentId = $territoryAssignment->exists ? $territoryAssignment->getKey() : null;

        if (TerritoryAssignment::territoryHasOpenAssignment($territoryAssignment->territory_id, $ignoredAssignmentId)) {
            throw ValidationException::withMessages([
                'territory_id' => __('validation.territory_assignment.already_assigned'),
            ]);
        }
    }

    /**
     * Handle the TerritoryAssignment "created" event.
     */
    public function created(TerritoryAssignment $territoryAssignment): void
    {
        // Carica la relazione territory prima di inviare la notifica
        $territoryAssignment->load('territory');
        $territoryAssignment->assignee->notify(new TerritoryAssignedNotification($territoryAssignment));
    }

    /**
     * Handle the TerritoryAssignment "updated" event.
     */
    public function updated(TerritoryAssignment $territoryAssignment): void
    {
        if ($territoryAssignment->wasChanged('ended_at') && $territoryAssignment->ended_at !== null) {
            // Carica la relazione territory prima di inviare la notifica
            $territoryAssignment->load(['territory', 'shared']);
            $territoryAssignment->assignee->notify(new TerritoryReturnedNotification($territoryAssignment));

            // Remove shared URL when assignment ends
            if ($territoryAssignment->shared) {
                $territoryAssignment->shared->delete();
            }
        }
    }

    /**
     * Handle the TerritoryAssignment "deleted" event.
     */
    public function deleted(TerritoryAssignment $territoryAssignment): void
    {
        // Remove shared URL when assignment is deleted
        if ($territoryAssignment->shared) {
            $territoryAssignment->shared->delete();
        }
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
        // Remove shared URL when assignment is force deleted
        if ($territoryAssignment->shared) {
            $territoryAssignment->shared->delete();
        }
    }
}
