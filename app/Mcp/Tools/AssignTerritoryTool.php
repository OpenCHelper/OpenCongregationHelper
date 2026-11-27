<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('assign_territory')]
#[Description('Assign an available territory to a person, optionally for a campaign. The person is notified as when assigning from the app. Ask the user to confirm the territory and the person before calling it.')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class AssignTerritoryTool extends CongregationTool
{
    protected function isAllowed(User $user): bool
    {
        return $this->can('create', TerritoryAssignment::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        Gate::authorize('create', TerritoryAssignment::class);

        $validated = $request->validate([
            'territory_id' => ['required', 'integer'],
            'assignee_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'campaign_id' => ['nullable', 'integer', Rule::exists(Campaign::class, 'id')],
            'started_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirm_campaign_repeat' => ['nullable', 'boolean'],
        ]);

        $territory = Territory::query()->find($validated['territory_id']);

        if ($territory === null) {
            return $this->notFound('territory');
        }

        $campaignId = $validated['campaign_id'] ?? null;

        // A territory already worked in the campaign most likely should not go out again for it, as from the app
        // (`TerritoryAssignmentResource::getCampaignRepeatConfirmation()`): only once the user confirms it
        if (! ($validated['confirm_campaign_repeat'] ?? false) && TerritoryAssignment::territoryWorkedInCampaign($territory->id, $campaignId)) {
            $campaign = Campaign::query()->find($campaignId);

            return Response::error("Territory {$territory->name} has already been worked in the campaign {$campaign?->name}, so it most likely should not go out again for it. Tell the user and, only if they confirm, call this tool again with `confirm_campaign_repeat` set to true.");
        }

        // Only the date is given: a territory given today starts now (the observer's default), not at midnight
        $startedAt = isset($validated['started_at']) ? Carbon::parse($validated['started_at']) : null;

        if ($startedAt?->isToday()) {
            $startedAt = null;
        }

        // The observer refuses a territory already assigned or a campaign already ended (as a validation error); the
        // transaction makes the check atomic, as from the app (`CreateAssignmentAction`)
        $assignment = DB::transaction(fn (): TerritoryAssignment => $territory->assignments()->create(array_filter([
            'assignee_id' => $validated['assignee_id'],
            'campaign_id' => $campaignId,
            'started_at' => $startedAt,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $this->user()?->id,
        ], fn (mixed $value): bool => $value !== null)));

        $assignment->refresh()->load(['territory', 'campaign', 'assignee']);

        return $this->respond([
            'message' => "Territory {$territory->name} assigned to {$assignment->assignee?->getFullName()}.",
            'assignment' => McpPresenter::assignment($assignment),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'territory_id' => $schema->integer()->description('The id of the territory, which must be available (see `list_territories` with status `available`).')->required(),
            'assignee_id' => $schema->integer()->description('The id of the person (see `list_people`).')->required(),
            'campaign_id' => $schema->integer()->description('The id of a campaign not ended yet, if the territory is assigned for it (see `list_campaigns`).'),
            'started_at' => $schema->string()->format('date')->description('When the territory was given (YYYY-MM-DD), not in the future. Default today.'),
            'notes' => $schema->string()->description('Notes about the assignment.'),
            'confirm_campaign_repeat' => $schema->boolean()->description('Set to true only once the user confirmed to assign again a territory already worked in the same campaign.'),
        ];
    }
}
