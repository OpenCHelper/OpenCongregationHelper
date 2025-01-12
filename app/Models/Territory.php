<?php

namespace App\Models;

use App\HasParentSoftDeletable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Territory extends Model
{
    /** @use HasFactory<\Database\Factories\TerritoryFactory> */
    use HasFactory;
    use SoftDeletes;

    use HasParentSoftDeletable;

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

    public function assignments(): HasMany {
        return $this->hasMany(TerritoryAssignment::class);
    }

    public function lastAssignment(): HasOne {
        return $this->hasOne(TerritoryAssignment::class)->latest();
    }

    public function lastAssignee(): HasOneThrough {
        return $this->hasOneThrough(User::class, TerritoryAssignment::class);
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
