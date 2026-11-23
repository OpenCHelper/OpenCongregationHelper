<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_campaigns')]
#[Description('List the campaigns (special preaching periods, e.g. a convention invitation) with their dates, status and number of assignments.')]
#[IsReadOnly]
class ListCampaignsTool extends CongregationTool
{
    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', Campaign::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        Gate::authorize('viewAny', Campaign::class);

        $filters = $request->validate(['status' => ['nullable', 'in:all,active,not_terminated']]);

        $query = Campaign::query()
            ->withCount(['assignments', 'assignments as assignments_in_progress_count' => fn (Builder $query) => $query->whereNull('ended_at')])
            ->when(($filters['status'] ?? 'all') === 'active', fn (Builder $query) => $query->withActive())
            ->when(($filters['status'] ?? 'all') === 'not_terminated', fn (Builder $query) => $query->notTerminated())
            ->latest('valid_from');

        return $this->respond($this->paginate($request, $query, McpPresenter::campaign(...)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['all', 'active', 'not_terminated'])
                ->description('`active`: started and not ended; `not_terminated`: not ended (future ones included). Default `all`.'),
            ...$this->paginationSchema($schema),
        ];
    }
}
