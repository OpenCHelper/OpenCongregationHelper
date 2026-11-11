<?php

namespace App\Http\Controllers;

use App\Models\Territory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only API for territories.
 */
class TerritoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Collection<int, Territory>
     */
    public function index(): Collection
    {
        Gate::authorize('viewAny', Territory::class);

        return Territory::all();
    }

    /**
     * Display the specified resource.
     */
    public function show(Territory $territory): Territory
    {
        Gate::authorize('view', $territory);

        return $territory;
    }
}
