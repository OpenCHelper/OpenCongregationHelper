<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssignmentHistory extends Model
{
    // use SoftDeletes;

    protected $table = 'storico_uscite';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'data_uscita',
        'data_rientro',
        'tipo_campagna',
        'eliminato',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['territorio', 'assegnato_a', 'tipo_campagna'];

    /**
     * Get the Assignee associated with the territory.
     */
    public function assegnato_a(): HasOne {
        return $this->hasOne(Assignee::class, 'id', 'assegnato_a');
    }

    /**
     * Get the Territory associated with the territory.
     */
    public function territorio(): HasOne {
        return $this->hasOne(Territory::class, 'id', 'id_territorio');
    }

    /**
     * Get the CampaignType associated with the territory.
     */
    public function tipo_campagna(): HasOne {
        return $this->hasOne(AssignmentType::class, 'id', 'tipo_campagna');
    }
    
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_uscita' => 'datetime',
            'data_rientro' => 'datetime',
            'eliminato' => 'boolean',
        ];
    }
}
