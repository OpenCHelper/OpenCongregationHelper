<?php

namespace App\Models;

use Database\Factories\PublicationSubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A publisher receiving publications on paper regularly: their subscriptions, each one requested again every period.
 *
 * The subscriptions are stopped, never deleted, so that the history of the publisher is kept.
 */
class PublicationSubscriber extends Model
{
    /** @use HasFactory<PublicationSubscriberFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'notes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['user_id', 'notes']);
    }

    /**
     * Get the publisher receiving the publications.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all the subscriptions of the publisher, the stopped ones included.
     *
     * @return HasMany<PublicationSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(PublicationSubscription::class, 'subscriber_id');
    }

    /**
     * Get the subscriptions still requested again every period.
     *
     * @return HasMany<PublicationSubscription, $this>
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->active();
    }

    /**
     * Only the publishers with at least an active subscription.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('activeSubscriptions');
    }

    /**
     * Get the copies of the given publication (from the loaded active subscriptions).
     */
    public function quantityOf(Publication|int $publication): int
    {
        $publicationId = $publication instanceof Publication ? $publication->getKey() : $publication;

        return (int) $this->activeSubscriptions->firstWhere('publication_id', $publicationId)?->quantity;
    }

    /**
     * Only the publishers with an active subscription to the given publication.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReceiving(Builder $query, Publication|int $publication): Builder
    {
        $publicationId = $publication instanceof Publication ? $publication->getKey() : $publication;

        return $query->whereHas('activeSubscriptions', fn (Builder $subscriptions): Builder => $subscriptions
            ->where('publication_id', $publicationId)
            ->where('quantity', '>', 0));
    }

    /**
     * Update the active subscriptions to the given rows (one for each publication received):
     *
     * - a new publication starts a subscription today, which requests it right away;
     * - different copies apply to the next requests (the past ones keep theirs: that is the history);
     * - a publication no longer in the rows is stopped today, not deleted.
     *
     * @param  iterable<array{publication_id: int|string, quantity: int|string}>  $rows
     */
    public function syncSubscriptions(iterable $rows): void
    {
        DB::transaction(function () use ($rows): void {
            $active = $this->activeSubscriptions()->get()->keyBy('publication_id');
            $kept = [];

            foreach ($rows as $row) {
                $publicationId = (int) $row['publication_id'];
                $quantity = (int) $row['quantity'];
                $kept[] = $publicationId;

                $subscription = $active->get($publicationId);

                if ($subscription) {
                    $subscription->update(['quantity' => $quantity]);

                    continue;
                }

                $this->subscriptions()
                    ->create(['publication_id' => $publicationId, 'quantity' => $quantity, 'started_at' => today()])
                    ->renew();
            }

            $active->except($kept)->each(fn (PublicationSubscription $subscription) => $subscription->stop());
        });

        $this->unsetRelation('activeSubscriptions');
    }

    /**
     * Stop all the active subscriptions: the publisher leaves the list, but keeps the history.
     */
    public function stopSubscriptions(): void
    {
        $this->activeSubscriptions()->get()->each(fn (PublicationSubscription $subscription) => $subscription->stop());

        $this->unsetRelation('activeSubscriptions');
    }

    /**
     * Get the total copies of each publication, and how many publishers receive it, among the given publishers.
     *
     * @param  iterable<int>  $subscriberIds
     * @return array<int, array{copies: int, publishers: int}> keyed by the ID of the publication
     */
    public static function totals(iterable $subscriberIds): array
    {
        return PublicationSubscription::query()
            ->active()
            ->whereIn('subscriber_id', collect($subscriberIds)->all())
            ->where('quantity', '>', 0)
            ->groupBy('publication_id')
            ->select('publication_id')
            ->selectRaw('sum(quantity) as copies, count(*) as publishers')
            ->get()
            ->mapWithKeys(fn (PublicationSubscription $subscription): array => [$subscription->publication_id => [
                'copies' => (int) $subscription->getAttribute('copies'),
                'publishers' => (int) $subscription->getAttribute('publishers'),
            ]])
            ->all();
    }
}
