<?php

namespace App\Models;

use Spatie\Tags\Tag as SpatieTag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

use App\Models\Taggable;

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
        return $this->morphedByMany(\App\Models\Territory::class, 'taggable');
    }

    /**
     * Get all of the territory zones for the tag.
     */
    public function territoryZones(): MorphToMany
    {
        return $this->morphedByMany(\App\Models\TerritoryZone::class, 'taggable');
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
