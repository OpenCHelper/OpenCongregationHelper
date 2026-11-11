<?php

namespace App\Http\Controllers;

use App\Models\TerritoryZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only API for territory zones.
 */
class TerritoryZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Collection<int, TerritoryZone>
     */
    public function index(): Collection
    {
        Gate::authorize('viewAny', TerritoryZone::class);

        return TerritoryZone::all();
    }

    /**
     * Display the specified resource.
     */
    public function show(TerritoryZone $zone): TerritoryZone
    {
        Gate::authorize('view', $zone);

        return $zone;
    }
}
