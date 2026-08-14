<?php

namespace App\Models;

use Database\Factories\TerritoryAssignmentFactory;
use App\Observers\TerritoryAssignmentObserver;
use App\Traits\HasSharedUrl;
use App\Traits\HasGuestViewPage;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

#[ObservedBy(TerritoryAssignmentObserver::class)]
class TerritoryAssignment extends Model
{
    /** @use HasFactory<TerritoryAssignmentFactory> */
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    use HasSharedUrl;
    use HasGuestViewPage;

    /**
     * Override the guest page route to use the correct path
     * for TerritoryAssignment (called `SharedAssignment` in the guest panel).
     */
    protected ?string $guestPageRoute = '/guest/shared-assignment';

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
        'started_at',
        'ended_at',
        'notes',
        'assignee_id',
        'territory_id',
        'campaign_id',
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
    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    // /**
    //  * Get the Territory associated with the territory.
    //  */
    public function territory(): BelongsTo {
        return $this->belongsTo(Territory::class);
    }

    // /**
    //  * Get the Campaign associated with the territory.
    //  */
    public function campaign(): BelongsTo {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
