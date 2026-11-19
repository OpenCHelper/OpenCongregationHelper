<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The copies of a publication received by a publisher.
 */
class PeriodicalSubscriptionItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'periodical_subscription_id',
        'publication_id',
        'quantity',
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
        ];
    }

    /**
     * @return BelongsTo<PeriodicalSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PeriodicalSubscription::class, 'periodical_subscription_id');
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }
}
