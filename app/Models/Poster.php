<?php

namespace App\Models;

use Database\Factories\PosterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A magnetic poster of the displays used in public witnessing (e.g. carts and stands).
 */
class Poster extends Model
{
    /** @use HasFactory<PosterFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'title',
        'image_path',
        'notes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['code', 'title', 'image_path', 'notes']);
    }

    /**
     * Get the quantities of the poster in each location.
     *
     * @return HasMany<PosterStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(PosterStock::class);
    }

    /**
     * Get the copies of the poster kept in the given location (from the loaded stocks).
     */
    public function quantityIn(StorageLocation|int $location): int
    {
        $locationId = $location instanceof StorageLocation ? $location->getKey() : $location;

        return (int) $this->stocks->firstWhere('storage_location_id', $locationId)?->quantity;
    }

    /**
     * Get the copies of the poster in every location (from the loaded stocks).
     */
    public function totalQuantity(): int
    {
        return (int) $this->stocks->sum('quantity');
    }
}
