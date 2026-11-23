<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_publications')]
#[Description('List the paper publications that can be requested (e.g. magazines, books, brochures), with their code and whether they are received regularly.')]
#[IsReadOnly]
class ListPublicationsTool extends CongregationTool
{
    /**
     * Who can see the requests can see what can be requested.
     */
    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', Publication::class) || $this->can('viewAny', PublicationRequest::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        abort_unless($this->isAllowed($this->user()), 403);

        $filters = $request->validate(['include_inactive' => ['nullable', 'boolean']]);

        $query = Publication::query()
            ->when(! ($filters['include_inactive'] ?? false), fn (Builder $query) => $query->where('is_active', true))
            ->ordered();

        return $this->respond($this->paginate($request, $query, McpPresenter::publication(...)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_inactive' => $schema->boolean()->description('Also list the publications no longer offered. Default false.'),
            ...$this->paginationSchema($schema),
        ];
    }
}
