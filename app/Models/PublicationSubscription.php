<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
     * month every `renewal_months` of the publication. `null` when it is not requested again (stopped, or the
     * publication does not renew).
     */
    public function getNextRequestDate(): ?Carbon
    {
        $months = $this->loadMissing('publication')->publication->renewal_months;

        if (! $this->isActive() || ! $months) {
            return null;
        }

        $last = $this->requests()->latest('requested_at')->value('requested_at');

        return $last
            ? Carbon::parse($last)->startOfMonth()->addMonthsNoOverflow($months)
            : $this->started_at->copy();
    }

    /**
     * Make the requests due by the given day (today by default): one for each period, so that a missed run of the
     * scheduler is caught up. Safe to call again: nothing is requested twice for the same period.
     *
     * @return list<PublicationRequest> the requests made
     */
    public function renew(?CarbonInterface $day = null): array
    {
        $day = Carbon::parse($day ?? today())->startOfDay();
        $made = [];

        while (($next = $this->getNextRequestDate()) && $next->lessThanOrEqualTo($day)) {
            $made[] = $this->requests()->create([
                'user_id' => $this->loadMissing('subscriber')->subscriber->user_id,
                'publication_id' => $this->publication_id,
                'quantity' => $this->quantity,
                'requested_at' => $next->toDateString(),
            ]);
        }

        return $made;
    }
}
