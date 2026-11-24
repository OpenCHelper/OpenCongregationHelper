<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\PublicationRequest;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_people')]
#[Description('Find the people of the congregation (the publishers territories can be assigned to) by name, with how many territories each one has at the moment. Contacts are not shared.')]
#[IsReadOnly]
class ListPeopleTool extends CongregationTool
{
    /**
     * Who can see the users, or give them territories or publications (as the forms of the app list them).
     */
    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', User::class)
            || $this->can('create', TerritoryAssignment::class)
            || $this->can('create', PublicationRequest::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        abort_unless($this->isAllowed($this->user()), 403);

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);

        // How many territories each one has is shown only to who can see the assignments
        $canSeeAssignments = $this->can('viewAny', TerritoryAssignment::class);

        $query = User::query()
            ->when($canSeeAssignments, fn (Builder $query) => $query->withCount(['assignments as assignments_in_progress_count' => fn (Builder $query) => $query->whereNull('ended_at')]))
            // Every word must be in the first or last name (e.g. "Mario Rossi")
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                foreach (preg_split('/\s+/', trim($search)) ?: [] as $word) {
                    $query->where(fn (Builder $query) => $query->whereLike('first_name', '%'.$word.'%')->orWhereLike('last_name', '%'.$word.'%'));
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name');

        return $this->respond($this->paginate($request, $query, fn (User $user): array => [
            ...McpPresenter::person($user),
            ...($canSeeAssignments ? ['assignments_in_progress_count' => (int) $user->getAttribute('assignments_in_progress_count')] : []),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Part of the first or last name.'),
            ...$this->paginationSchema($schema),
        ];
    }
}
