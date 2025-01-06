<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Log;

class Territory extends Model
{
    // use SoftDeletes;

    protected $table = 'territori';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_territorio',
        'immagine',
        'note',
        'zona',
        'dimensione',
        'territorio_commerciale',
        'geojson',
    ];

    protected $casts = [
        'territorio_commerciale' => 'boolean',
        // 'geojson' => 'array',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    // protected $with = ['zona'];

    /**
     * Get the Zone associated with the territory.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(TerritoryZone::class, 'zona', 'id');
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
