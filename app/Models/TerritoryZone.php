<?php

namespace App\Models;

use Database\Factories\TerritoryZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Tags\HasTags;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class TerritoryZone extends Model
{
    /** @use HasFactory<TerritoryZoneFactory> */
    use HasFactory;
    use SoftDeletes;

    use HasTags;
    use LogsActivity;
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable();
    }

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
