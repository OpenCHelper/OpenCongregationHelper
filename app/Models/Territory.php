<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'territorio_commerciale' => 'boolean',
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
}
