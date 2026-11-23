<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * A tool of the MCP server (`CongregationServer`), used by an AI assistant on behalf of the user who connected it.
 *
 * The user is the one of the OAuth access token: every tool goes through the same policies as the app, and is listed
 * only to the users who can use it (`shouldRegister()`), so that the assistant does not try what would be denied.
 */
abstract class CongregationTool extends Tool
{
    /**
     * Most items a list returns at once (`limit`).
     */
    public const MAX_LIMIT = 100;

    /**
     * Items a list returns when the assistant does not ask for a number.
     */
    public const DEFAULT_LIMIT = 25;

    /**
     * Whether the tool is listed to the current user.
     */
    public function shouldRegister(): bool
    {
        $user = $this->user();

        return $user !== null && $this->isAllowed($user);
    }

    /**
     * Whether the given user can use the tool (by default, every user).
     */
    protected function isAllowed(User $user): bool
    {
        return true;
    }

    /**
     * The user who connected the assistant.
     */
    protected function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Whether the current user can do the given ability (as `Gate::allows()`).
     */
    protected function can(string $ability, mixed $arguments = []): bool
    {
        return Gate::forUser($this->user())->allows($ability, $arguments);
    }

    /**
     * The `limit` and `page` parameters of the lists.
     *
     * @return array<string, mixed>
     */
    protected function paginationSchema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Maximum number of items to return (1-'.self::MAX_LIMIT.', default '.self::DEFAULT_LIMIT.').')
                ->min(1)
                ->max(self::MAX_LIMIT),
            'page' => $schema->integer()
                ->description('Page of results to return, starting from 1.')
                ->min(1),
        ];
    }

    /**
     * Paginate a list, returning its items (mapped by the given callback) and how to get the next page.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $present
     * @return array{items: list<array<string, mixed>>, total: int, page: int, has_more: bool}
     */
    protected function paginate(Request $request, Builder $query, callable $present): array
    {
        $validated = Validator::validate($request->all(), [
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $query->paginate(
            perPage: (int) ($validated['limit'] ?? self::DEFAULT_LIMIT),
            page: (int) ($validated['page'] ?? 1),
        );

        return [
            'items' => array_values(array_map($present, $paginator->items())),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }

    /**
     * The error returned when the record asked does not exist (or was deleted).
     */
    protected function notFound(string $record): Response
    {
        return Response::error("No {$record} found with this id: use a list tool to find it.");
    }

    /**
     * A response with structured content (for the assistants that read it) and its JSON text (for the others).
     *
     * @param  array<string, mixed>  $content
     */
    protected function respond(array $content): ResponseFactory
    {
        return Response::structured($content);
    }
}
