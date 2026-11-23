<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Department;
use App\Models\TerritoryAssignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_my_account')]
#[Description('Get who the user is (name, roles, departments), the territories assigned to them at the moment, and what the other tools let them do. Call it first to know how to help.')]
#[IsReadOnly]
class GetMyAccountTool extends CongregationTool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user();

        abort_if($user === null, 401);

        $assignments = $user->assignments()
            ->whereNull('ended_at')
            ->with(['territory', 'campaign'])
            ->oldest('started_at')
            ->get();

        return $this->respond([
            'user' => [
                ...McpPresenter::person($user),
                'roles' => $user->getRoleNames()->values()->all(),
                'departments' => $user->departments()->get()
                    ->map(fn (Department $department): array => [
                        'name' => $department->getLabel(),
                        'role' => $department->getRelationValue('pivot')?->getAttribute('role')?->value,
                    ])
                    ->all(),
            ],
            'my_assignments_in_progress' => $assignments
                ->map(fn (TerritoryAssignment $assignment): array => [
                    ...McpPresenter::assignment($assignment),
                    'can_request_return' => $assignment->canRequestReturn(),
                ])
                ->all(),
            'today' => now()->toDateString(),
            'app_url' => url('/app'),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
