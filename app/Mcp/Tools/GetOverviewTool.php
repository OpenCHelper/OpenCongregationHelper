<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Campaign;
use App\Models\PublicationRequest;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use App\Settings\TerritorySettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_overview')]
#[Description('Get a summary of the state of the territories: how many are available or assigned, the assignments needing attention (out for too long, overdue, return requested), the active campaigns and the publication requests to handle. Use it to answer "what should I do?" questions.')]
#[IsReadOnly]
class GetOverviewTool extends CongregationTool
{
    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', Territory::class) && $this->can('viewAny', TerritoryAssignment::class);
    }

    public function handle(Request $request, TerritorySettings $settings): ResponseFactory
    {
        Gate::authorize('viewAny', Territory::class);
        Gate::authorize('viewAny', TerritoryAssignment::class);

        $inProgress = fn () => TerritoryAssignment::query()->whereNull('ended_at');

        $overview = [
            'today' => now()->toDateString(),
            'territories' => [
                'total' => Territory::query()->count(),
                'available' => Territory::query()->unassigned()->count(),
                'assigned' => Territory::query()->assigned()->count(),
                'never_worked' => Territory::query()->whereDoesntHave('assignments', fn ($query) => $query->returned())->count(),
            ],
            'assignments' => [
                'in_progress' => $inProgress()->count(),
                "out_for_more_than_{$settings->stale_after_days}_days" => $inProgress()->where('started_at', '<', now()->subDays($settings->stale_after_days))->count(),
                "out_for_more_than_{$settings->dead_after_days}_days" => $inProgress()->where('started_at', '<', now()->subDays($settings->dead_after_days))->count(),
                'overdue' => TerritoryAssignment::query()->overdue()->count(),
                'due_soon' => TerritoryAssignment::query()->dueSoon()->count(),
                'return_requested' => $inProgress()->whereNotNull('return_requested_at')->count(),
                'returned_in_the_last_30_days' => TerritoryAssignment::query()->returned()->where('ended_at', '>=', now()->subDays(30))->count(),
            ],
        ];

        if ($this->can('viewAny', Campaign::class)) {
            $overview['active_campaigns'] = Campaign::query()->withActive()->orderBy('valid_until')->get()
                ->map(fn (Campaign $campaign): array => McpPresenter::campaign($campaign))
                ->all();
        }

        if ($this->can('viewAny', PublicationRequest::class)) {
            $overview['publication_requests'] = [
                'to_order' => PublicationRequest::query()->toOrder()->count(),
                'waiting_for_delivery' => PublicationRequest::query()->ordered()->count(),
            ];
        }

        return $this->respond($overview);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
