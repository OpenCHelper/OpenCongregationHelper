<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A publication received regularly by a publisher: it is requested again every period of the publication (e.g. The
 * Watchtower every month), from its start until it is stopped.
 */
class PublicationSubscription extends Model
{
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subscriber_id',
        'publication_id',
        'quantity',
        'started_at',
        'ended_at',
        'last_period_on',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'started_at' => 'date',
            'ended_at' => 'date',
            'last_period_on' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['publication_id', 'quantity', 'started_at', 'ended_at']);
    }

    /**
     * @return BelongsTo<PublicationSubscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(PublicationSubscriber::class, 'subscriber_id');
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * Get the requests made by the subscription, one for each period.
     *
     * @return HasMany<PublicationRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(PublicationRequest::class, 'subscription_id');
    }

    /**
     * Only the subscriptions not stopped.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('ended_at'));
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Stop requesting the publication again, from the given day (today by default).
     */
    public function stop(?CarbonInterface $day = null): void
    {
        $this->update(['ended_at' => ($day ?? today())->toDateString()]);
    }

    /**
     * Get the day the next request is due: the start of the subscription for the first one, then the 1st of the
     * month every `renewal_months` of the publication, counted from the last period requested (`last_period_on`), so
     * that a request deleted or moved to another day is not made again. `null` when it is not requested again
     * (stopped, or the publication does not renew).
     */
    public function getNextRequestDate(): ?Carbon
    {
        $months = $this->loadMissing('publication')->publication->renewal_months;

        if (! $this->isActive() || ! $months) {
            return null;
        }

        return $this->last_period_on
            ? $this->last_period_on->copy()->startOfMonth()->addMonthsNoOverflow($months)
            : $this->started_at->copy();
    }

    /**
     * Make the request of the current period, if it is due by the given day (today by default). The periods missed
     * meanwhile (e.g. while the renewal of the publication was turned off) are skipped, not requested all at once.
     * Safe to call again: nothing is requested twice for the same period.
     *
     * @return list<PublicationRequest> the request made, if any
     */
    public function renew(?CarbonInterface $day = null): array
    {
        $day = Carbon::parse($day ?? today())->startOfDay();
        $months = (int) $this->loadMissing('publication')->publication->renewal_months;
        $next = $this->getNextRequestDate();

        if (! $next || $next->greaterThan($day)) {
            return [];
        }

        // The last period due by the day
        while (($following = $next->copy()->startOfMonth()->addMonthsNoOverflow($months))->lessThanOrEqualTo($day)) {
            $next = $following;
        }

        // Together, so that a failure in between doesn't request the same period again on the next run (the unique
        // index on the subscription and day rejects that too)
        return DB::transaction(function () use ($next): array {
            $request = $this->requests()->create([
                'user_id' => $this->loadMissing('subscriber')->subscriber->user_id,
                'publication_id' => $this->publication_id,
                'quantity' => $this->quantity,
                'requested_at' => $next->toDateString(),
            ]);

            $this->update(['last_period_on' => $next->toDateString()]);

            return [$request];
        });
    }
}
