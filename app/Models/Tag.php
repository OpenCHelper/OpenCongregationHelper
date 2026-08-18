<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Spatie\Tags\Tag as SpatieTag;

/**
 * Tag model for polymorphic tagging.
 *
 * This model extends the Spatie Tags package to provide additional functionality
 * such as dynamic retrieval of related models.
 */
class Tag extends SpatieTag
{
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
     * Get all taggables for this tag.
     */
    public function taggables(): Collection
    {
        return Taggable::where('tag_id', $this->id)
            ->get();
    }
}
