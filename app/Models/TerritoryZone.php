<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TerritoryZone extends Model
{
    protected $table = 'zone';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome_zona',
        'color',
    ];

    /**
     * Get the territories belonging to this zone.
     */
    public function territories(): HasMany
    {
        return $this->hasMany(Territory::class, 'zona', 'id');
    }
}
