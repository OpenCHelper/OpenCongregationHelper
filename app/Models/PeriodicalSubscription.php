<?php

namespace App\Models;

use App\Enums\Periodical;
use Database\Factories\PeriodicalSubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The periodicals that a publisher receives on paper, with the number of copies of each one.
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
        'quantities',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantities' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['user_id', 'quantities', 'notes']);
    }

    /**
     * Get the publisher receiving the periodicals.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the copies of the given periodical.
     */
    public function quantityOf(Periodical $periodical): int
    {
        return (int) ($this->quantities[$periodical->value] ?? 0);
    }

    /**
     * Only the subscriptions receiving at least a copy of the given periodical.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReceiving(Builder $query, Periodical $periodical): Builder
    {
        return $query->where("quantities->{$periodical->value}", '>', 0);
    }

    /**
     * Get the total copies of each periodical among the given subscriptions.
     *
     * @param  iterable<self>  $subscriptions
     * @return array<string, array{copies: int, publishers: int}>
     */
    public static function totals(iterable $subscriptions): array
    {
        $totals = [];

        foreach (Periodical::cases() as $periodical) {
            $totals[$periodical->value] = ['copies' => 0, 'publishers' => 0];
        }

        foreach ($subscriptions as $subscription) {
            foreach (Periodical::cases() as $periodical) {
                $quantity = $subscription->quantityOf($periodical);

                if ($quantity > 0) {
                    $totals[$periodical->value]['copies'] += $quantity;
                    $totals[$periodical->value]['publishers']++;
                }
            }
        }

        return $totals;
    }
}
