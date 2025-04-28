<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Tags\HasTags;

class TerritoryZone extends Model
{
    /** @use HasFactory<\Database\Factories\TerritoryZoneFactory> */
    use HasFactory;
    use SoftDeletes;

    use HasTags;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'color',
    ];

    /**
     * Get the territories belonging to this zone.
     */
    public function territories(): HasMany
    {
        return $this->hasMany(Territory::class);
    }
}
