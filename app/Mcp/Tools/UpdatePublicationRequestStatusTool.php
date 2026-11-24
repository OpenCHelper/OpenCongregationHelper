<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_publication_requests_status')]
#[Description('Mark one or more publication requests as ordered from the branch, or as delivered to the publishers.')]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class UpdatePublicationRequestStatusTool extends CongregationTool
{
    /**
     * Most requests updated at once.
     */
    public const MAX_REQUESTS = 100;

    protected function isAllowed(User $user): bool
    {
        return $this->can('viewAny', PublicationRequest::class) && $this->can('Update:PublicationRequest');
    }

    public function handle(Request $request): ResponseFactory
    {
        abort_unless($this->isAllowed($this->user()), 403);

        $validated = $request->validate([
            'request_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_REQUESTS],
            'request_ids.*' => ['integer', 'distinct'],
            'status' => ['required', 'in:ordered,delivered'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $date = Carbon::parse($validated['date'] ?? today())->toDateString();

        $requests = PublicationRequest::query()->with(['user', 'publication'])->findMany($validated['request_ids']);

        $requests->each(fn (PublicationRequest $publicationRequest) => Gate::authorize('update', $publicationRequest));

        // The steps stay in order, as from the app (e.g. a request is not delivered before it was requested)
        $requests->each(fn (PublicationRequest $publicationRequest) => $publicationRequest->update(
            $validated['status'] === 'ordered'
                ? ['ordered_at' => max($date, $publicationRequest->requested_at->toDateString())]
                : ['delivered_at' => max($date, $publicationRequest->requested_at->toDateString(), (string) $publicationRequest->ordered_at?->toDateString())],
        ));

        return $this->respond([
            'message' => "{$requests->count()} request(s) marked as {$validated['status']}.",
            'not_found_ids' => array_values(array_diff($validated['request_ids'], $requests->modelKeys())),
            'requests' => $requests->map(fn (PublicationRequest $publicationRequest): array => McpPresenter::publicationRequest($publicationRequest))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'request_ids' => $schema->array()->items($schema->integer())->min(1)->max(self::MAX_REQUESTS)
                ->description('The ids of the requests (see `list_publication_requests`).')->required(),
            'status' => $schema->string()->enum(['ordered', 'delivered'])->description('The new status of the requests.')->required(),
            'date' => $schema->string()->format('date')->description('When they were ordered or delivered (YYYY-MM-DD), not in the future. Default today.'),
        ];
    }
}
