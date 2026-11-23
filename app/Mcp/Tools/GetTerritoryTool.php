<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Tag;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_territory')]
#[Description('Get the details of a territory: its description and notes, its current assignment, how many days it usually takes to be worked, and its latest assignments.')]
#[IsReadOnly]
class GetTerritoryTool extends CongregationTool
{
    /**
     * Assignments of the history returned.
     */
    public const HISTORY_LENGTH = 10;

    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', Territory::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['territory_id' => ['required', 'integer']]);

        $territory = static::query()->find($validated['territory_id']);

        if ($territory === null) {
            return $this->notFound('territory');
        }

        Gate::authorize('view', $territory);

        return $this->respond($this->details($territory));
    }

    /**
     * The query of the territories, with what their details show.
     *
     * @return Builder<Territory>
     */
    public static function query(): Builder
    {
        return Territory::query()
            ->with(['territoryZone', 'currentAssignment.campaign', 'tags'])
            ->withMax(['assignments as last_returned_at' => fn (Builder $query) => $query->returned()], 'ended_at')
            ->withCount('assignments');
    }

    /**
     * The details of a territory (loaded by `query()`), its latest assignments too when the user can see them.
     *
     * @return array<string, mixed>
     */
    public function details(Territory $territory): array
    {
        $details = [
            ...McpPresenter::territory($territory),
            'description' => $territory->description ?: null,
            'notes' => $territory->notes ?: null,
            'tags' => $territory->tags->map(fn (Tag $tag): string => $tag->name)->values()->all(),
            'has_map' => $territory->geojson !== null,
            'assignments_count' => $territory->getAttribute('assignments_count'),
            'average_days_to_work' => ($average = $territory->averageCoverageDays()) !== null ? round($average, 1) : null,
        ];

        if ($this->can('viewAny', TerritoryAssignment::class)) {
            $details['latest_assignments'] = $territory->assignments()
                ->with('campaign')
                ->latest('started_at')
                ->limit(self::HISTORY_LENGTH)
                ->get()
                ->map(fn (TerritoryAssignment $assignment): array => McpPresenter::assignment($assignment, withTerritory: false))
                ->all();
        }

        return array_filter($details, fn (mixed $value): bool => $value !== null);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'territory_id' => $schema->integer()->description('The id of the territory (see `list_territories`).')->required(),
        ];
    }
}
