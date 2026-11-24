<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_publication_requests')]
#[Description('List the requests of paper publications made by the publishers: the ones still to order, the ones ordered and waiting for their delivery, or the delivered ones. Useful to prepare the order for the branch.')]
#[IsReadOnly]
class ListPublicationRequestsTool extends CongregationTool
{
    /**
     * The statuses a request can be filtered by.
     */
    public const STATUSES = ['all', 'pending', 'to_order', 'ordered', 'delivered'];

    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', PublicationRequest::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        Gate::authorize('viewAny', PublicationRequest::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'publisher_id' => ['nullable', 'integer'],
            'publication_id' => ['nullable', 'integer'],
        ]);

        $query = PublicationRequest::query()
            ->with(['user', 'publication'])
            ->when($filters['publisher_id'] ?? null, fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['publication_id'] ?? null, fn (Builder $query, int $id) => $query->where('publication_id', $id))
            ->latest('requested_at')
            ->latest('id');

        match ($filters['status'] ?? 'pending') {
            'pending' => $query->pending(),
            'to_order' => $query->toOrder(),
            'ordered' => $query->ordered(),
            'delivered' => $query->delivered(),
            default => $query,
        };

        return $this->respond($this->paginate($request, $query, McpPresenter::publicationRequest(...)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(self::STATUSES)->description(
                '`pending`: not delivered yet (default); `to_order`: not ordered yet; `ordered`: waiting for the delivery; `delivered`; `all`.'
            ),
            'publisher_id' => $schema->integer()->description('Only the requests of this person (see `list_people`).'),
            'publication_id' => $schema->integer()->description('Only the requests of this publication (see `list_publications`).'),
            ...$this->paginationSchema($schema),
        ];
    }
}
