<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTerritoryZoneRequest;
use App\Http\Requests\UpdateTerritoryZoneRequest;
use App\Models\TerritoryZone;
use Illuminate\Support\Facades\Gate;

class TerritoryZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', TerritoryZone::class);

        return TerritoryZone::all();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTerritoryZoneRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TerritoryZone $zone)
    {
        Gate::authorize('view', $zone);

        return $zone;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TerritoryZone $zone)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTerritoryZoneRequest $request, TerritoryZone $zone)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TerritoryZone $zone)
    {
        //
    }
}
