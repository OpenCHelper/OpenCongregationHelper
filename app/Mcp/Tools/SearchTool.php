<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPresenter;
use App\Models\Campaign;
use App\Models\Publication;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The search of ChatGPT's connectors (and Deep Research): the matching records, each with the id that `fetch` reads.
 *
 * @see https://platform.openai.com/docs/mcp
 */
#[Name('search')]
#[Description('Search the congregation by name: territories, zones, campaigns, people and publications. Returns the matching records with their id, to read with `fetch`.')]
#[IsReadOnly]
class SearchTool extends CongregationTool
{
    /**
     * Results returned for each kind of record.
     */
    public const RESULTS_PER_KIND = 10;

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate(['query' => ['required', 'string', 'max:255']]);

        $words = array_values(array_filter(preg_split('/\s+/', trim($validated['query'])) ?: []));

        $results = [
            ...$this->search('territory', Territory::class, ['name', 'description', 'notes'], $words, fn (Territory $territory): string => $territory->name),
            ...$this->search('zone', TerritoryZone::class, ['name'], $words, fn (TerritoryZone $zone): string => $zone->name),
            ...$this->search('campaign', Campaign::class, ['name', 'short_name', 'description'], $words, fn (Campaign $campaign): string => $campaign->name),
            ...$this->search('person', User::class, ['first_name', 'last_name'], $words, fn (User $user): string => $user->getFullName()),
            ...$this->search('publication', Publication::class, ['name', 'code'], $words, fn (Publication $publication): string => $publication->name),
        ];

        return $this->respond(['results' => $results]);
    }

    /**
     * The records of a kind whose columns hold every word of the query, when the user can see that kind.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @param  list<string>  $columns
     * @param  list<string>  $words
     * @param  callable(TModel): string  $title
     * @return list<array{id: string, title: string, url: string}>
     */
    private function search(string $kind, string $model, array $columns, array $words, callable $title): array
    {
        if (! FetchTool::canFetch($kind)) {
            return [];
        }

        return $model::query()
            ->where(function (Builder $query) use ($columns, $words): void {
                foreach ($words as $word) {
                    $query->where(fn (Builder $query) => collect($columns)->each(
                        fn (string $column) => $query->orWhereLike($column, '%'.$word.'%'),
                    ));
                }
            })
            ->limit(self::RESULTS_PER_KIND)
            ->get()
            ->map(fn ($record): array => [
                'id' => "{$kind}:{$record->getKey()}",
                'title' => McpPresenter::kindLabel($kind).': '.$title($record),
                'url' => FetchTool::url($kind, $record),
            ])
            ->values()
            ->all();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Words to find in the names (e.g. a territory, a person, a campaign).')->required(),
        ];
    }
}
