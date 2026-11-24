<?php

namespace App\Mcp\Tools;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Resources\TerritoryZones\TerritoryZoneResource;
use App\Filament\Resources\Users\UserResource;
use App\Mcp\McpPresenter;
use App\Models\Campaign;
use App\Models\Publication;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The fetch of ChatGPT's connectors (and Deep Research): the whole content of a record found by `search`.
 *
 * @see https://platform.openai.com/docs/mcp
 */
#[Name('fetch')]
#[Description('Read a record found by `search` (or listed by another tool), from its id: `territory:<id>`, `zone:<id>`, `campaign:<id>`, `person:<id>`, `publication:<id>` or `assignment:<id>`.')]
#[IsReadOnly]
class FetchTool extends CongregationTool
{
    /**
     * The kinds of records, with their model.
     *
     * @var array<string, class-string<Model>>
     */
    public const KINDS = [
        'territory' => Territory::class,
        'zone' => TerritoryZone::class,
        'campaign' => Campaign::class,
        'person' => User::class,
        'publication' => Publication::class,
        'assignment' => TerritoryAssignment::class,
    ];

    /**
     * Assignments listed in the records of a zone, a campaign or a person.
     */
    public const ASSIGNMENTS_SHOWN = 25;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['id' => ['required', 'string', 'max:64']]);

        [$kind, $key] = array_pad(explode(':', $validated['id'], 2), 2, '');

        if (! array_key_exists($kind, self::KINDS) || ! ctype_digit($key)) {
            return Response::error('Unknown id: use the id of a result of `search` (e.g. `territory:12`).');
        }

        $record = $kind === 'territory' ? GetTerritoryTool::query()->find($key) : self::KINDS[$kind]::query()->find($key);

        if ($record === null || ! self::canFetch($kind, $record)) {
            return $this->notFound($kind);
        }

        [$title, $content] = $this->content($kind, $record);

        return $this->respond([
            'id' => $validated['id'],
            'title' => McpPresenter::kindLabel($kind).': '.$title,
            'text' => (string) json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'url' => self::url($kind, $record),
            'metadata' => ['kind' => $kind],
        ]);
    }

    /**
     * Whether the current user can read the records of a kind (a given one, when passed): as the other tools.
     */
    public static function canFetch(string $kind, ?Model $record = null): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return match ($kind) {
            'person' => $record?->is($user) || app(ListPeopleTool::class)->shouldRegister(),
            'publication' => app(ListPublicationsTool::class)->shouldRegister(),
            // The assignees can read their own assignments (see `TerritoryAssignmentPolicy::view()`)
            'assignment' => $record !== null ? Gate::allows('view', $record) : Gate::allows('viewAny', TerritoryAssignment::class),
            default => $record !== null ? Gate::allows('view', $record) : Gate::allows('viewAny', self::KINDS[$kind]),
        };
    }

    /**
     * The page of the record in the app, when the user can open it (the app otherwise).
     */
    public static function url(string $kind, Model $record): string
    {
        $resource = match ($kind) {
            'territory' => TerritoryResource::class,
            'zone' => TerritoryZoneResource::class,
            'campaign' => CampaignResource::class,
            'person' => UserResource::class,
            'assignment' => TerritoryAssignmentResource::class,
            default => null,
        };

        return $resource !== null && Gate::allows('view', $record)
            ? $resource::getUrl('view', ['record' => $record], panel: 'app')
            : url('/app');
    }

    /**
     * The title and the content of a record.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function content(string $kind, Model $record): array
    {
        $canSeeAssignments = $this->can('viewAny', TerritoryAssignment::class);

        return match (true) {
            $record instanceof Territory => [$record->name, app(GetTerritoryTool::class)->details($record)],
            $record instanceof TerritoryZone => [$record->name, array_filter([
                ...McpPresenter::zone($record->loadCount('territories')),
                'territories' => $this->can('viewAny', Territory::class)
                    ? $record->territories()
                        ->with(['territoryZone', 'currentAssignment.campaign'])
                        ->orderBy('name')
                        ->get()
                        ->map(McpPresenter::territory(...))
                        ->all()
                    : null,
            ], fn (mixed $value): bool => $value !== null)],
            $record instanceof Campaign => [$record->name, [
                ...McpPresenter::campaign($canSeeAssignments
                    ? $record->loadCount(['assignments', 'assignments as assignments_in_progress_count' => fn (Builder $query) => $query->whereNull('ended_at')])
                    : $record),
                'assignments' => $canSeeAssignments ? $this->assignments($record->assignments()->getQuery()) : null,
            ]],
            $record instanceof User => [$record->getFullName(), [
                ...McpPresenter::person($record),
                'assignments_in_progress' => $canSeeAssignments || $record->is($this->user())
                    ? $this->assignments($record->assignments()->getQuery()->whereNull('ended_at'))
                    : null,
            ]],
            $record instanceof Publication => [$record->name, McpPresenter::publication($record)],
            $record instanceof TerritoryAssignment => [
                $record->loadMissing('territory')->territory?->name ?? "#{$record->id}",
                McpPresenter::assignment($record->loadMissing(['territory', 'campaign', 'assignee'])),
            ],
            default => ["#{$record->getKey()}", []],
        };
    }

    /**
     * The latest assignments of a query.
     *
     * @param  Builder<TerritoryAssignment>  $query
     * @return list<array<string, mixed>>
     */
    private function assignments(Builder $query): array
    {
        return $query->with(['territory', 'campaign'])
            ->latest('started_at')
            ->limit(self::ASSIGNMENTS_SHOWN)
            ->get()
            ->map(fn (TerritoryAssignment $assignment): array => McpPresenter::assignment($assignment))
            ->values()
            ->all();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The id of the record, e.g. `territory:12` (see `search`).')->required(),
        ];
    }
}
