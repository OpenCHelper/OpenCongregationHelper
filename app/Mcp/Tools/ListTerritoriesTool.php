<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_territories')]
#[Description('List the territories of the congregation, with their zone, whether they are assigned (and to whom) and when they were last returned. Use the filters to find, for example, the available territories not worked for a long time.')]
#[IsReadOnly]
class ListTerritoriesTool extends CongregationTool
{
    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', Territory::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        Gate::authorize('viewAny', Territory::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'zone_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:all,available,assigned'],
            'is_commercial' => ['nullable', 'boolean'],
            'not_worked_since' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:name,last_returned_at'],
        ]);

        $query = Territory::query()
            ->with(['territoryZone', 'currentAssignment.campaign'])
            ->withMax(['assignments as last_returned_at' => fn (Builder $query) => $query->returned()], 'ended_at')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($filters['zone_id'] ?? null, fn (Builder $query, int $zone) => $query->where('territory_zone_id', $zone))
            ->when(($filters['status'] ?? 'all') === 'available', fn (Builder $query) => $query->unassigned())
            ->when(($filters['status'] ?? 'all') === 'assigned', fn (Builder $query) => $query->assigned())
            ->when(isset($filters['is_commercial']), fn (Builder $query) => $query->where('is_commercial', (bool) $filters['is_commercial']))
            // Never returned, or last returned before the date
            ->when($filters['not_worked_since'] ?? null, fn (Builder $query, string $date) => $query->whereDoesntHave(
                'assignments',
                fn (Builder $query) => $query->returned()->where('ended_at', '>=', $date),
            ));

        ($filters['sort'] ?? 'name') === 'last_returned_at'
            // The territories never returned first
            ? $query->orderByRaw('last_returned_at is not null')->orderBy('last_returned_at')->orderBy('name')
            : $query->orderBy('name');

        return $this->respond($this->paginate($request, $query, McpPresenter::territory(...)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Part of the name of the territory.'),
            'zone_id' => $schema->integer()->description('Only the territories of this zone (see `list_territory_zones`).'),
            'status' => $schema->string()->enum(['all', 'available', 'assigned'])
                ->description('`available`: not assigned to anyone; `assigned`: with an assignment in progress. Default `all`.'),
            'is_commercial' => $schema->boolean()->description('Only the commercial (true) or residential (false) territories.'),
            'not_worked_since' => $schema->string()->format('date')
                ->description('Only the territories not returned since this date (YYYY-MM-DD), the never worked ones included.'),
            'sort' => $schema->string()->enum(['name', 'last_returned_at'])
                ->description('`last_returned_at` lists first the territories not worked for the longest time. Default `name`.'),
            ...$this->paginationSchema($schema),
        ];
    }
}
