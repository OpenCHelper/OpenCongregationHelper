<?php

namespace App\Models;

use Database\Factories\Spatie\Tags\TagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Spatie\Tags\Tag as SpatieTag;

/**
 * Tag model for polymorphic tagging.
 *
 * This model extends the Spatie Tags package to provide additional functionality
 * such as dynamic retrieval of related models.
 *
 * Tag names and slugs are stored and looked up in a single locale (see `getLocale()`),
 * whatever the interface language of the user.
 */
class Tag extends SpatieTag
{
    /**
     * Get the locale tag names and slugs are stored, displayed and looked up in.
     *
     * Spatie's tags use the current application locale by default, which changes with the
     * interface language chosen by each user: a tag stored as `{"en": "x"}` was not found
     * by name under the Italian interface, so saving a form created a duplicate `{"it": "x"}`
     * tag and moved the record to it. Using one fixed locale keeps a tag a single record.
     */
    public static function getLocale(): string
    {
        return config('tags.locale', 'en');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    /**
     * Get all of the territories for the tag.
     */
    public function territories(): MorphToMany
    {
        return $this->morphedByMany(Territory::class, 'taggable');
    }

    /**
     * Get all of the territory zones for the tag.
     */
    public function territoryZones(): MorphToMany
    {
        return $this->morphedByMany(TerritoryZone::class, 'taggable');
    }

    /**
     * Count the territories and the zones (not deleted) that use at least one of the given tags, which lose them when
     * the tags are deleted.
     *
     * @param  array<int, int|string>  $tagIds
     * @return array{territories: int, zones: int}
     */
    public static function countUsages(array $tagIds): array
    {
        $usingTheTags = fn (Builder $query): Builder => $query->whereKey($tagIds);

        return [
            'territories' => Territory::whereHas('tags', $usingTheTags)->count(),
            'zones' => TerritoryZone::whereHas('tags', $usingTheTags)->count(),
        ];
    }

    /**
     * Get all taggables for this tag.
     */
    public function taggables(): Collection
    {
        return Taggable::where('tag_id', $this->id)
            ->get();
    }
}
