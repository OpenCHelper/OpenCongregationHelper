<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignee extends Model
{
    protected $table = 'proclamatori';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome',
        'cognome',
        'data_modifica',
        'eliminato',
    ];

    
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_modifica' => 'datetime',
            'eliminato' => 'boolean',
        ];
    }
}
