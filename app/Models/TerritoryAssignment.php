<?php

namespace App\Models;

use App\Observers\TerritoryAssignmentObserver;
use App\Traits\HasGuestViewPage;
use App\Traits\HasSharedUrl;
use Database\Factories\TerritoryAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(TerritoryAssignmentObserver::class)]
class TerritoryAssignment extends Model
{
    /** @use HasFactory<TerritoryAssignmentFactory> */
    use HasFactory;

    use HasGuestViewPage;
    use HasSharedUrl;
    use LogsActivity;
    use SoftDeletes;

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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    // /**
    //  * Get the Territory associated with the territory.
    //  */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    // /**
    //  * Get the Campaign associated with the territory.
    //  */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine whether the given territory has an assignment in progress (not returned yet),
     * optionally ignoring a specific assignment (e.g. the one being edited).
     */
    public static function territoryHasOpenAssignment(int $territoryId, ?int $ignoredAssignmentId = null): bool
    {
        return static::query()
            ->where('territory_id', $territoryId)
            ->whereNull('ended_at')
            ->when($ignoredAssignmentId !== null, fn (Builder $query) => $query->whereKeyNot($ignoredAssignmentId))
            ->exists();
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
