<?php

namespace App\Observers;

use App\Models\TerritoryZone;

class TerritoryZoneObserver
{
    /**
     * Handle the TerritoryZone "deleting" event, fired by both soft and force deletes.
     *
     * A zone that still has territories (not deleted) cannot be deleted: its territories would be
     * hidden together with it (see `HasParentSoftDeletable`), breaking their assignments. They must
     * be moved to another zone or deleted first.
     *
     * A zone cannot be force deleted while it has any territory, deleted ones included: the foreign
     * key cascade would permanently delete them, together with the history of their assignments.
     * They must be moved to another zone or force deleted first.
     *
     * The `parentNotDeleted` scope is removed so that the territories of a zone that is
     * already deleted (e.g. before being force deleted) are counted too.
     *
     * @return false|null false cancels the deletion; null lets it (and the other "deleting" listeners) go on
     */
    public function deleting(TerritoryZone $territoryZone): ?bool
    {
        $territories = $territoryZone->territories()->withoutGlobalScope('parentNotDeleted');

        if ($territoryZone->isForceDeleting()) {
            $territories->withTrashed();
        }

        if ($territories->exists()) {
            return false;
        }

        return null;
    }
}
