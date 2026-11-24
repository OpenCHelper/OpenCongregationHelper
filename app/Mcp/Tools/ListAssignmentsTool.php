<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_assignments')]
#[Description('List the assignments of the territories (who has which territory, since when, and when it was returned). Filter them to find, for example, the territories out for too long, the overdue ones (whose campaign has ended), the ones whose return was requested, or the history of a person or territory.')]
#[IsReadOnly]
class ListAssignmentsTool extends CongregationTool
{
    /**
     * The statuses an assignment can be filtered by.
     */
    public const STATUSES = ['all', 'in_progress', 'returned', 'overdue', 'due_soon', 'return_requested'];

    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', TerritoryAssignment::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        Gate::authorize('viewAny', TerritoryAssignment::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'assignee_id' => ['nullable', 'integer'],
            'territory_id' => ['nullable', 'integer'],
            'campaign_id' => ['nullable', 'integer'],
            'started_before' => ['nullable', 'date'],
            'started_after' => ['nullable', 'date'],
            'returned_after' => ['nullable', 'date'],
        ]);

        $query = TerritoryAssignment::query()
            ->with(['territory', 'campaign'])
            ->when($filters['assignee_id'] ?? null, fn (Builder $query, int $id) => $query->where('assignee_id', $id))
            ->when($filters['territory_id'] ?? null, fn (Builder $query, int $id) => $query->where('territory_id', $id))
            ->when($filters['campaign_id'] ?? null, fn (Builder $query, int $id) => $query->where('campaign_id', $id))
            ->when($filters['started_before'] ?? null, fn (Builder $query, string $date) => $query->whereDate('started_at', '<', $date))
            ->when($filters['started_after'] ?? null, fn (Builder $query, string $date) => $query->whereDate('started_at', '>=', $date))
            ->when($filters['returned_after'] ?? null, fn (Builder $query, string $date) => $query->whereDate('ended_at', '>=', $date));

        match ($filters['status'] ?? 'all') {
            'in_progress' => $query->whereNull('ended_at'),
            'returned' => $query->whereNotNull('ended_at'),
            'overdue' => $query->overdue(),
            'due_soon' => $query->dueSoon(),
            'return_requested' => $query->whereNull('ended_at')->whereNotNull('return_requested_at'),
            default => $query,
        };

        // The ones in progress for the longest time first, then the latest returned
        $query->orderByRaw('ended_at is not null')->orderBy('started_at')->orderByDesc('ended_at');

        return $this->respond($this->paginate($request, $query, McpPresenter::assignment(...)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(self::STATUSES)->description(
                '`in_progress`: not returned yet; `returned`; `overdue`: in progress although its campaign has ended; '
                .'`due_soon`: in progress and its campaign is about to end; `return_requested`: the assignee asked to return it. Default `all`.'
            ),
            'assignee_id' => $schema->integer()->description('Only the assignments of this person (see `list_people`).'),
            'territory_id' => $schema->integer()->description('Only the assignments of this territory.'),
            'campaign_id' => $schema->integer()->description('Only the assignments of this campaign (see `list_campaigns`).'),
            'started_before' => $schema->string()->format('date')->description('Only the assignments started before this date (YYYY-MM-DD), e.g. to find the ones out for too long.'),
            'started_after' => $schema->string()->format('date')->description('Only the assignments started on or after this date (YYYY-MM-DD).'),
            'returned_after' => $schema->string()->format('date')->description('Only the assignments returned on or after this date (YYYY-MM-DD).'),
            ...$this->paginationSchema($schema),
        ];
    }
}
