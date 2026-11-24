<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_territory_zones')]
#[Description('List the zones the territories are grouped in, with their number of territories.')]
#[IsReadOnly]
class ListTerritoryZonesTool extends CongregationTool
{
    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', TerritoryZone::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        Gate::authorize('viewAny', TerritoryZone::class);

        $query = TerritoryZone::query()->withCount('territories')->orderBy('name');

        return $this->respond($this->paginate($request, $query, McpPresenter::zone(...)));
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->paginationSchema($schema);
    }
}
