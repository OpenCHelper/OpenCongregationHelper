<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('return_territory')]
#[Description('Record that a territory was returned (worked), ending its assignment in progress. Ask the user to confirm the territory before calling it.')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class ReturnTerritoryTool extends CongregationTool
{
    protected function isAllowed(User $user): bool
    {
        return $this->can('Update:TerritoryAssignment');
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'territory_id' => ['required', 'integer'],
            'returned_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $territory = Territory::query()->with('currentAssignment.campaign')->find($validated['territory_id']);

        if ($territory === null) {
            return $this->notFound('territory');
        }

        $assignment = $territory->currentAssignment;

        if ($assignment === null) {
            return Response::error("Territory {$territory->name} is not assigned to anyone: there is nothing to return.");
        }

        Gate::authorize('update', $assignment);

        $assignment->ended_at = isset($validated['returned_at'])
            // The time of day of the request, as the assignments returned from the app
            ? Carbon::parse($validated['returned_at'])->setTimeFrom(now())
            : now();

        if (filled($validated['notes'] ?? null)) {
            $assignment->notes = trim(implode("\n\n", array_filter([$assignment->notes, $validated['notes']])));
        }

        $assignment->setRelation('territory', $territory);
        $assignment->save();

        return $this->respond([
            'message' => "Territory {$territory->name} returned by {$assignment->assignee?->getFullName()}.",
            'assignment' => McpPresenter::assignment($assignment),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'territory_id' => $schema->integer()->description('The id of the assigned territory (see `list_territories` with status `assigned`).')->required(),
            'returned_at' => $schema->string()->format('date')->description('When it was returned (YYYY-MM-DD), not in the future. Default today.'),
            'notes' => $schema->string()->description('Notes to add to the assignment (e.g. what was not worked).'),
        ];
    }
}
