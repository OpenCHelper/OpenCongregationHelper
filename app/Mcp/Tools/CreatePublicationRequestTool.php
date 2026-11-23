<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('create_publication_request')]
#[Description('Record that a publisher asked for some copies of a publication, once. Regular subscriptions (e.g. the magazines every month) are managed from the app.')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class CreatePublicationRequestTool extends CongregationTool
{
    protected function isAllowed(User $user): bool
    {
        return $this->can('create', PublicationRequest::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        Gate::authorize('create', PublicationRequest::class);

        $validated = $request->validate([
            'publisher_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'publication_id' => ['required', 'integer', Rule::exists(Publication::class, 'id')->where('is_active', true)],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'publication_id.exists' => 'The publication does not exist or is no longer offered (see `list_publications`).',
        ]);

        $publicationRequest = PublicationRequest::query()->create([
            'user_id' => $validated['publisher_id'],
            'publication_id' => $validated['publication_id'],
            'quantity' => $validated['quantity'] ?? 1,
            'requested_at' => today(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $publicationRequest->load(['user', 'publication']);

        return $this->respond([
            'message' => 'Request recorded.',
            'request' => McpPresenter::publicationRequest($publicationRequest),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'publisher_id' => $schema->integer()->description('The id of the person asking for it (see `list_people`).')->required(),
            'publication_id' => $schema->integer()->description('The id of an offered publication (see `list_publications`).')->required(),
            'quantity' => $schema->integer()->min(1)->max(999)->description('Number of copies. Default 1.'),
            'notes' => $schema->string()->description('Notes about the request (e.g. the language).'),
        ];
    }
}
