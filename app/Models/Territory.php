<?php

namespace App\Models;

use App\HasParentSoftDeletable;
use App\Observers\TerritoryObserver;
use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Tags\HasTags;

#[ObservedBy(TerritoryObserver::class)]
class Territory extends Model
{
    /** @use HasFactory<TerritoryFactory> */
    use HasFactory;

    use HasParentSoftDeletable;
    use HasTags;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['name', 'description', 'geojson', 'notes']);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'image_path',
        'geojson',
        'notes',
        'is_commercial',
        'territory_zone_id',
    ];

    protected $casts = [
        'is_commercial' => 'boolean',
        // 'geojson' => 'array',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    // protected $with = [];

    /**
     * Get the Zone associated with the territory.
     */
    public function territoryZone(): BelongsTo
    {
        return $this->belongsTo(TerritoryZone::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TerritoryAssignment::class);
    }

    /**
     * Get the current active assignment for this territory.
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(TerritoryAssignment::class)
            ->whereNull('ended_at')
            ->latest('started_at');
    }

    /**
     * Get the most recently returned (ended) assignment for this territory.
     */
    public function lastReturnedAssignment(): HasOne
    {
        return $this->hasOne(TerritoryAssignment::class)->ofMany(
            ['ended_at' => 'max'],
            fn (Builder $query) => $query->whereNotNull('ended_at'),
        );
    }

    /**
     * Get the current assignee directly through the assignment.
     */
    public function currentAssignee(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            TerritoryAssignment::class,
            'territory_id',    // Foreign key on territory_assignments table
            'id',              // Foreign key on users table
            'id',              // Local key on territories table
            'assignee_id'      // Local key on territory_assignments table
        )->whereNull('territory_assignments.ended_at')
            ->latest('territory_assignments.started_at');
    }

    /**
     * Scope a query to only include assigned territories.
     */
    public function scopeAssigned(Builder $query): void
    {
        $query->whereHas('assignments', function ($query) {
            $query->whereNull('ended_at');
        });
    }

    /**
     * Scope a query to only include unassigned territories.
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->whereDoesntHave('assignments', function ($query) {
            $query->whereNull('ended_at');
        });
    }

    public function geojson(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value) {
                if (is_null($value) || empty($value)) {
                    return null;
                }

                return $value;
            },
            set: function (mixed $value) {
                if (is_null($value) || empty($value)) {
                    return null;
                }

                // FeatureCollections with no features should be saved as null
                // Es.: {"type":"FeatureCollection","features":[]}
                $object = $value;
                if (is_string($value)) {
                    $object = json_decode($value);
                }

                if (empty($object->features ?? [])) {
                    return null;
                }

                return $value;
            },
        );
    }
}
