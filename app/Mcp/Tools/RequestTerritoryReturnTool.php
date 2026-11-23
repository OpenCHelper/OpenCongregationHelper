<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\TerritoryAssignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('request_territory_return')]
#[Description('Ask the territory department to close one of the user\'s own assignments (e.g. when they finished working the territory): its members are notified. See `get_my_account` for the assignments that can be returned.')]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class RequestTerritoryReturnTool extends CongregationTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['assignment_id' => ['required', 'integer']]);

        $assignment = TerritoryAssignment::query()->with(['territory', 'campaign'])->find($validated['assignment_id']);

        if ($assignment === null) {
            return $this->notFound('assignment');
        }

        Gate::authorize('requestReturn', $assignment);

        if (! $assignment->requestReturn($this->user())) {
            return Response::error($assignment->ended_at !== null
                ? 'This assignment was already returned.'
                : 'The return of this assignment was already requested.');
        }

        return $this->respond([
            'message' => 'The territory department was asked to close the assignment.',
            'assignment' => McpPresenter::assignment($assignment),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'assignment_id' => $schema->integer()->description('The id of one of the user\'s assignments in progress (see `get_my_account`).')->required(),
        ];
    }
}
