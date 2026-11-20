<?php

namespace App\Models;

use App\Enums\PublicationRequestStatus;
use Database\Factories\PublicationRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A request of a publication by a publisher: made once (e.g. a book), or again every period by a subscription (e.g.
 * The Watchtower). It is requested, optionally ordered from the branch, and then delivered.
 */
class PublicationRequest extends Model
{
    /** @use HasFactory<PublicationRequestFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'publication_id',
        'subscription_id',
        'quantity',
        'requested_at',
        'ordered_at',
        'delivered_at',
        'notes',
        'created_by',
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
            'requested_at' => 'date',
            'ordered_at' => 'date',
            'delivered_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The requests made from the interface remember who entered them; the renewed ones are made by the scheduler
        static::creating(function (self $request): void {
            $request->created_by ??= Auth::id();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['user_id', 'publication_id', 'quantity', 'requested_at', 'ordered_at', 'delivered_at', 'notes']);
    }

    /**
     * Get the publisher who requested the publication.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * Get the subscription that made the request again, if it is a renewal.
     *
     * @return BelongsTo<PublicationSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PublicationSubscription::class, 'subscription_id');
    }

    /**
     * Get the user who entered the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatus(): PublicationRequestStatus
    {
        return match (true) {
            $this->delivered_at !== null => PublicationRequestStatus::Delivered,
            $this->ordered_at !== null => PublicationRequestStatus::Ordered,
            default => PublicationRequestStatus::Requested,
        };
    }

    public function isRenewal(): bool
    {
        return $this->subscription_id !== null;
    }

    /**
     * Only the requests still to order from the branch, or to retrieve from the storage.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeToOrder(Builder $query): Builder
    {
        return $query->whereNull('ordered_at')->whereNull('delivered_at');
    }

    /**
     * Only the requests ordered and waiting for their delivery.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->whereNotNull('ordered_at')->whereNull('delivered_at');
    }

    /**
     * Only the requests not delivered yet.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('delivered_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->whereNotNull('delivered_at');
    }
}
