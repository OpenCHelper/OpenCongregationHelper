<?php

namespace App\Mcp;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\Campaign;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * The data that the MCP tools return about the records of the app: only what an assistant needs to help managing them,
 * with plain values (dates as `Y-m-d`, statuses as their code) and the link to open each record in the app.
 *
 * The relations that are presented must be eager loaded, as lazy loading is forbidden outside of production.
 */
final class McpPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function territory(Territory $territory): array
    {
        $currentAssignment = $territory->relationLoaded('currentAssignment') ? $territory->currentAssignment : null;

        return array_filter([
            'id' => $territory->id,
            'name' => $territory->name,
            'zone' => $territory->relationLoaded('territoryZone') && $territory->territoryZone !== null
                ? ['id' => $territory->territoryZone->id, 'name' => $territory->territoryZone->name]
                : null,
            'is_commercial' => (bool) $territory->is_commercial,
            'status' => $territory->relationLoaded('currentAssignment')
                ? ($currentAssignment === null ? 'available' : 'assigned')
                : null,
            'current_assignment' => $currentAssignment !== null ? self::assignment($currentAssignment, withTerritory: false) : null,
            'last_returned_at' => self::date($territory->getAttribute('last_returned_at')),
            'url' => self::url(fn (): string => TerritoryResource::getUrl('view', ['record' => $territory], panel: 'app'), 'view', $territory),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function assignment(TerritoryAssignment $assignment, bool $withTerritory = true): array
    {
        return array_filter([
            'id' => $assignment->id,
            'territory' => $withTerritory && $assignment->relationLoaded('territory') && $assignment->territory !== null
                ? ['id' => $assignment->territory->id, 'name' => $assignment->territory->name]
                : null,
            'assignee' => $assignment->relationLoaded('assignee') && $assignment->assignee !== null
                ? self::person($assignment->assignee)
                : null,
            'campaign' => $assignment->relationLoaded('campaign') && $assignment->campaign !== null
                ? ['id' => $assignment->campaign->id, 'name' => $assignment->campaign->name]
                : null,
            'status' => self::assignmentStatus($assignment),
            'started_at' => self::date($assignment->started_at),
            'ended_at' => self::date($assignment->ended_at),
            'return_requested_at' => self::date($assignment->return_requested_at),
            'days_out' => $assignment->started_at !== null
                ? (int) $assignment->started_at->copy()->startOfDay()->diffInDays(($assignment->ended_at ?? now())->copy()->startOfDay())
                : null,
            'notes' => $assignment->notes ?: null,
            'url' => self::url(fn (): string => TerritoryAssignmentResource::getUrl('view', ['record' => $assignment], panel: 'app'), 'view', $assignment),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The status of an assignment, as filtered by the `list_assignments` tool.
     */
    public static function assignmentStatus(TerritoryAssignment $assignment): string
    {
        return match (true) {
            $assignment->ended_at !== null => 'returned',
            $assignment->relationLoaded('campaign') && $assignment->isOverdue() => 'overdue',
            $assignment->return_requested_at !== null => 'return_requested',
            $assignment->relationLoaded('campaign') && $assignment->isDueSoon() => 'due_soon',
            default => 'in_progress',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function campaign(Campaign $campaign): array
    {
        return array_filter([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'short_name' => $campaign->short_name ?: null,
            'description' => $campaign->description ?: null,
            'status' => $campaign->getStatus()->value,
            'valid_from' => self::date($campaign->valid_from),
            'valid_until' => self::date($campaign->valid_until),
            'assignments_count' => $campaign->getAttribute('assignments_count'),
            'assignments_in_progress_count' => $campaign->getAttribute('assignments_in_progress_count'),
            'url' => self::url(fn (): string => CampaignResource::getUrl('view', ['record' => $campaign], panel: 'app'), 'view', $campaign),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function zone(TerritoryZone $zone): array
    {
        return array_filter([
            'id' => $zone->id,
            'name' => $zone->name,
            'territories_count' => $zone->getAttribute('territories_count'),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * A person, without their contacts: the assistant only needs to tell them apart.
     *
     * @return array{id: int, name: string}
     */
    public static function person(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->getFullName()];
    }

    /**
     * @return array<string, mixed>
     */
    public static function publication(Publication $publication): array
    {
        return array_filter([
            'id' => $publication->id,
            'name' => $publication->name,
            'code' => $publication->code ?: null,
            'category' => $publication->category?->value,
            'renewal_months' => $publication->renewal_months ?: null,
            'is_active' => (bool) $publication->is_active,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicationRequest(PublicationRequest $request): array
    {
        return array_filter([
            'id' => $request->id,
            'publisher' => $request->relationLoaded('user') && $request->user !== null ? self::person($request->user) : null,
            'publication' => $request->relationLoaded('publication') && $request->publication !== null
                ? ['id' => $request->publication->id, 'name' => $request->publication->name]
                : null,
            'quantity' => $request->quantity,
            'status' => $request->getStatus()->value,
            'is_renewal' => $request->isRenewal(),
            'requested_at' => self::date($request->requested_at),
            'ordered_at' => self::date($request->ordered_at),
            'delivered_at' => self::date($request->delivered_at),
            'notes' => $request->notes ?: null,
        ], fn (mixed $value): bool => $value !== null);
    }

    public static function date(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        // Aggregates (e.g. `withMax()`) are the raw values of the database
        return $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }

    /**
     * The link to a page of the app, only when the user can open it.
     *
     * @param  callable(): string  $url
     */
    private static function url(callable $url, string $ability, mixed $record): ?string
    {
        return Gate::allows($ability, $record) ? $url() : null;
    }
}
