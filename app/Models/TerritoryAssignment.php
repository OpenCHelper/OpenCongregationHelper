<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TerritoryAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\TerritoryAssignmentFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'date_start',
        'date_end',
        'notes',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['assignee'];

    /**
     * Get the Assignee associated with the territory.
     */
    public function assignee(): HasOne {
        return $this->hasOne(User::class, 'id', 'assigned_to');
    }

    // /**
    //  * Get the Territory associated with the territory.
    //  */
    // public function territorio(): HasOne {
    //     return $this->hasOne(Territory::class, 'id', 'id_territorio');
    // }

    // /**
    //  * Get the CampaignType associated with the territory.
    //  */
    // public function tipo_campagna(): HasOne {
    //     return $this->hasOne(TerritoryAssignmentType::class, 'id', 'tipo_campagna');
    // }
    
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
