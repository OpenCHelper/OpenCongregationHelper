<?php

namespace App\Models;

use Database\Factories\PeriodicalSubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The publications that a publisher receives on paper, with the number of copies of each one.
 */
class PeriodicalSubscription extends Model
{
    /** @use HasFactory<PeriodicalSubscriptionFactory> */
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
     * Get the copies of each publication received.
     *
     * @return HasMany<PeriodicalSubscriptionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PeriodicalSubscriptionItem::class);
    }

    /**
     * Get the copies of the given publication (from the loaded items).
     */
    public function quantityOf(Publication|int $publication): int
    {
        $publicationId = $publication instanceof Publication ? $publication->getKey() : $publication;

        return (int) $this->items->firstWhere('publication_id', $publicationId)?->quantity;
    }

    /**
     * Get the copies of each publication, as in the forms (keyed by `Publication::getFormKey()`).
     *
     * @return array<string, int>
     */
    public function getFormQuantities(): array
    {
        return $this->items
            ->mapWithKeys(fn (PeriodicalSubscriptionItem $item): array => ['p'.$item->publication_id => $item->quantity])
            ->all();
    }

    /**
     * Save the subscription with the copies of each publication given (keyed by `Publication::getFormKey()`): the
     * publications with no copies are removed, the ones not given are left as they are (e.g. no longer offered).
     *
     * @param  array{user_id?: int, notes?: ?string, quantities?: array<string, int|string|null>}  $data
     */
    public function saveWithQuantities(array $data): self
    {
        DB::transaction(function () use ($data): void {
            $this->fill(collect($data)->only(['user_id', 'notes'])->all())->save();

            foreach ($data['quantities'] ?? [] as $key => $quantity) {
                $publicationId = (int) ltrim((string) $key, 'p');
                $quantity = (int) $quantity;

                if ($quantity > 0) {
                    $this->items()->updateOrCreate(['publication_id' => $publicationId], ['quantity' => $quantity]);
                } else {
                    $this->items()->where('publication_id', $publicationId)->delete();
                }
            }
        });

        return $this->load('items');
    }

    /**
     * Only the subscriptions receiving at least a copy of the given publication.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReceiving(Builder $query, Publication|int $publication): Builder
    {
        $publicationId = $publication instanceof Publication ? $publication->getKey() : $publication;

        return $query->whereHas('items', fn (Builder $items): Builder => $items
            ->where('publication_id', $publicationId)
            ->where('quantity', '>', 0));
    }

    /**
     * Get the total copies of each publication, and how many publishers receive it, among the given subscriptions.
     *
     * @param  iterable<int>  $subscriptionIds
     * @return array<int, array{copies: int, publishers: int}> keyed by the ID of the publication
     */
    public static function totals(iterable $subscriptionIds): array
    {
        return PeriodicalSubscriptionItem::query()
            ->whereIn('periodical_subscription_id', collect($subscriptionIds)->all())
            ->where('quantity', '>', 0)
            ->groupBy('publication_id')
            ->select('publication_id')
            ->selectRaw('sum(quantity) as copies, count(*) as publishers')
            ->get()
            ->mapWithKeys(fn (PeriodicalSubscriptionItem $item): array => [$item->publication_id => [
                'copies' => (int) $item->getAttribute('copies'),
                'publishers' => (int) $item->getAttribute('publishers'),
            ]])
            ->all();
    }
}
