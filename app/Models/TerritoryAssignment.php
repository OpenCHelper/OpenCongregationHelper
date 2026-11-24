<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\DepartmentKey;
use App\Notifications\TerritoryReturnRequestedNotification;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
        'return_requested_at',
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
     * Whether the assignment is still in progress although its campaign has already ended.
     */
    public function isOverdue(): bool
    {
        return $this->ended_at === null
            && $this->campaign?->valid_until !== null
            && $this->campaign->valid_until->isPast();
    }

    /**
     * Whether the assignment is still in progress and its campaign is about to end
     * (i.e. the campaign is expiring or almost expired).
     */
    public function isDueSoon(): bool
    {
        return $this->ended_at === null
            && $this->campaign !== null
            && in_array($this->campaign->getStatus(), [CampaignStatus::IS_EXPIRING, CampaignStatus::ALMOST_EXPIRED], true);
    }

    /**
     * Whether the return of the assignment, still in progress, has been asked to the territory department.
     */
    public function isReturnRequested(): bool
    {
        return $this->ended_at === null && $this->return_requested_at !== null;
    }

    /**
     * Whether the assignment can be shared (and its link opened): only while it is in progress, so that a returned
     * territory is not published again.
     */
    public function isShareable(): bool
    {
        return $this->ended_at === null && ! $this->trashed();
    }

    /**
     * Whether the return of the assignment can be asked: it is still in progress and it was not asked already.
     */
    public function canRequestReturn(): bool
    {
        return $this->ended_at === null && $this->return_requested_at === null && ! $this->trashed();
    }

    /**
     * Ask the territory department to close the assignment, notifying its members (but the one asking, if any).
     *
     * The request is recorded only once: asking again (e.g. from two devices at once) notifies nobody.
     *
     * @return bool Whether the return has been requested now.
     */
    public function requestReturn(?User $requester = null): bool
    {
        // Locked, so that only one of the requests made at the same time is recorded (and notified)
        $requested = DB::transaction(function (): bool {
            $assignment = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($assignment === null || ! $assignment->canRequestReturn()) {
                return false;
            }

            $assignment->update(['return_requested_at' => now()]);
            $this->setRawAttributes($assignment->getAttributes(), sync: true);

            return true;
        });

        if (! $requested) {
            return false;
        }

        $recipients = static::getReturnRequestRecipients()
            ->reject(fn (User $user): bool => $requester !== null && $user->is($requester));

        Notification::send($recipients, new TerritoryReturnRequestedNotification($this));

        return true;
    }

    /**
     * The users notified when the return of an assignment is asked: the members of the territory department.
     *
     * @return Collection<int, User>
     */
    public static function getReturnRequestRecipients(): Collection
    {
        return Department::for(DepartmentKey::Territories)->members()->get();
    }

    /**
     * Scope a query to only include the assignments in progress whose campaign has already ended.
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('ended_at')
            ->whereHas('campaign', fn (Builder $query) => $query->withExpired());
    }

    /**
     * Scope a query to only include the assignments in progress whose campaign is about to end.
     */
    public function scopeDueSoon(Builder $query): void
    {
        $query->whereNull('ended_at')
            ->whereHas('campaign', fn (Builder $query) => $query->expiring());
    }

    /**
     * Scope a query to only include the returned assignments,
     * ignoring the inconsistent ones that end before they start.
     */
    public function scopeReturned(Builder $query): void
    {
        $query->whereNotNull('ended_at')
            ->whereColumn('ended_at', '>=', 'started_at');
    }

    /**
     * The SQL expression of the duration of an assignment, in calendar days.
     */
    public static function durationInDaysExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'julianday(date(ended_at)) - julianday(date(started_at))',
            'pgsql' => '(ended_at::date - started_at::date)',
            'sqlsrv' => 'DATEDIFF(day, started_at, ended_at)',
            default => 'DATEDIFF(ended_at, started_at)',
        };
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
            'return_requested_at' => 'datetime',
        ];
    }
}
