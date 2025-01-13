<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'assignee_id',
        'territory_id',
        'territory_assignment_type_id',
        'created_by',
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
        return $this->hasOne(User::class, 'id', 'assignee_id');
    }

    // /**
    //  * Get the Territory associated with the territory.
    //  */
    public function territory(): BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    // /**
    //  * Get the CampaignType associated with the territory.
    //  */
    public function assignmentType(): BelongsTo {
        return $this->belongsTo(TerritoryAssignmentType::class, 'territory_assignment_type_id');
    }

    public function creator(): HasOne {
        return $this->hasOne(User::class, 'id', 'created_by');
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
